<?php

namespace App\Services;

use Symfony\Component\Panther\Client;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;

class HpcBrowserManager
{
    /**
     * Connect to an existing Chrome instance on port 9222 if available,
     * or spawn a new Chrome instance with the specified profile directory.
     */
    public static function createOrConnect(string $driverBin, string $profileDir, bool $headless = false): object
    {
        // 1. Ensure ChromeDriver is running on port 9515
        self::ensureChromeDriverRunning($driverBin);

        // 2. Check if active Chrome is listening on port 9222
        $fp = @fsockopen('127.0.0.1', 9222, $errno, $errstr, 1);
        if ($fp) {
            fclose($fp);
            try {
                $options = new ChromeOptions();
                $options->setExperimentalOption('debuggerAddress', '127.0.0.1:9222');
                $capabilities = DesiredCapabilities::chrome();
                $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

                $driver = RemoteWebDriver::create('http://127.0.0.1:9515', $capabilities, 10000, 10000);
                return new class($driver) {
                    private RemoteWebDriver $driver;
                    public function __construct(RemoteWebDriver $driver) { $this->driver = $driver; }
                    public function request(string $method, string $url): void { $this->driver->get($url); }
                    public function get(string $url): void { $this->driver->get($url); }
                    public function getCurrentURL(): string { return $this->driver->getCurrentURL(); }
                    public function executeScript(string $script, array $args = []) { return $this->driver->executeScript($script, $args); }
                    public function getWebDriver(): RemoteWebDriver { return $this->driver; }
                    public function takeScreenshot(string $path): void { $this->driver->takeScreenshot($path); }
                    public function quit(): void { /* Keep user's active Chrome open */ }
                };
            } catch (\Throwable $e) {
                // Fallback to normal launch if connect fails
            }
        }

        // 3. Otherwise launch a new Chrome instance
        if (file_exists($driverBin) && ! is_executable($driverBin)) {
            @chmod($driverBin, 0755);
        }

        $_SERVER['PANTHER_NO_HEADLESS'] = $headless ? '0' : '1';
        putenv('PANTHER_NO_HEADLESS=' . ($headless ? '0' : '1'));

        $pantherClient = Client::createChromeClient($driverBin, [
            '--user-data-dir=' . $profileDir,
            '--window-size=1400,900',
            '--no-sandbox',
            '--disable-dev-shm-usage',
        ]);

        return new class($pantherClient) {
            private Client $client;
            public function __construct(Client $client) { $this->client = $client; }
            public function request(string $method, string $url): void { $this->client->request($method, $url); }
            public function get(string $url): void { $this->client->request('GET', $url); }
            public function getCurrentURL(): string { return $this->client->getCurrentURL(); }
            public function executeScript(string $script, array $args = []) { return $this->client->executeScript($script, $args); }
            public function getWebDriver() { return $this->client->getWebDriver(); }
            public function takeScreenshot(string $path): void { $this->client->takeScreenshot($path); }
            public function quit(): void { $this->client->quit(); }
        };
    }

    private static function ensureChromeDriverRunning(string $driverBin): void
    {
        $fp = @fsockopen('127.0.0.1', 9515, $errno, $errstr, 0.5);
        if ($fp) {
            fclose($fp);
            return;
        }

        if (file_exists($driverBin)) {
            @chmod($driverBin, 0755);
            exec("nohup {$driverBin} --port=9515 > /dev/null 2>&1 &");
            usleep(800000);
        }
    }
}
