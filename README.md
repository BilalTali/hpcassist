# HPC Assist — PARAKH Digital HPC & UDISE+ Automation Suite

A modern Laravel-based portal designed to streamline **PARAKH Holistic Progress Card (HPC)** entry, **UDISE+ student data synchronization**, master Excel template generation, and automated assessment submission for schools and teachers.

---

## 🌟 Key Features

- **Automated UDISE+ Integration**:
  - Live browser-driven UDISE+ session detection and student roster sync with unmasked APAAR IDs.
  - Optional **Skip UDISE** workflow for offline/manual school setup.
- **Dynamic 4-Step Guided Workflow**:
  - **Step 1: UDISE+ Connect**: Instant sync or manual fallback.
  - **Step 2: School Profile**: School details, UDISE code, principal name, and contact settings.
  - **Step 3: HPC School Connect**: Headmaster PARAKH portal session connection.
  - **Step 4: Teacher Connect**: Dedicated teacher browser profile sessions and automated assessment execution.
- **Intelligent Excel Engine**:
  - Pre-formatted Part-by-Part evaluation sheets with color-coded stages, dropdown validation rules, and isolated collapsible outline brackets.
  - One-click template downloads: blank template or pre-filled directly with school student rosters and APAAR IDs.
- **Teacher Workspace & Automation**:
  - Teacher roster assignment, individual teacher session isolation, and batch execution tracking.
  - Live progress monitoring, real-time status badges, and error diagnostics.

---

## 🛠️ System Requirements

- **PHP**: 8.2 or higher (with `pdo`, `mbstring`, `zip`, `xml`, `curl` extensions)
- **Composer**: 2.x
- **Node.js & npm**: Node 18+ & npm 9+
- **Database**: SQLite (default) or MySQL / MariaDB
- **Browser Automation**:
  - Google Chrome (installed on host system)
  - ChromeDriver binary matching installed Chrome version (located in `drivers/chromedriver` or PATH)

---

## 🚀 Quick Start & Installation

### 1. Clone the Repository
```bash
git clone https://github.com/BilalTali/hpcassist.git
cd hpcassist
```

### 2. Install PHP & Node Dependencies
```bash
composer install
npm install
```

### 3. Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
```
Configure your database connection in `.env`. By default, SQLite can be used:
```bash
touch database/database.sqlite
```

### 4. Database Migrations & Seeders
```bash
php artisan migrate --seed
```

### 5. Build Frontend Assets
```bash
npm run build
```

### 6. Run the Application
You can run the full development environment with hot-reloading:
```bash
composer run dev
```
Or start the PHP server directly:
```bash
php artisan serve
```
Visit `http://127.0.0.1:8000` in your browser.

---

## 🧪 Running Tests

The test suite validates authentication, school workflows, teacher management, and template generation:
```bash
php artisan test
```

---

## 📂 Project Structure

```text
app/
├── Console/Commands/       # CLI commands for HPC discovery and automated runs
├── Http/Controllers/       # School, Teacher, UDISE+, and HPC controllers
├── Models/                 # School, Teacher, User, and Max Marks Eloquent models
├── Services/               # Browser and Chrome CDP Automation Manager
resources/
├── views/                  # Blade templates (Dashboard, HPC & Teacher workspaces)
storage/
└── app/hpc/                # Chrome sessions, profiles, and generated templates
```

---

## 📄 License

This software is released under the [MIT License](LICENSE).
