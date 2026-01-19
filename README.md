# PHP Legacy Accounting Application

A simplified accounting application intentionally designed with 2006-era PHP development patterns. This project simulates a French double-entry bookkeeping system with features typical of early web applications.

## Features

### Core Accounting
- **Double-entry bookkeeping** with draft/posted workflow
- **Chart of accounts** (Plan Comptable) management
- **Accounting journals** (Sales, Purchases, Bank, Miscellaneous)
- **VAT handling** with multiple rates (20%, 10%, 5.5%, exempt)
- **Period-based fiscal management** with lock/unlock capability

### Data Entry & Import
- **Accounting entries** with multi-line support and jQuery-driven UI
- **CSV import** for batch entry creation
- **Attachment support** for supporting documents (PDF, images)
- **Sequential piece numbering** generated on validation

### Bank Module
- **Bank account configuration**
- **Bank statement CSV import**
- **Manual reconciliation/matching** of statement lines with entries

### Customer/Vendor Management
- **Third parties** (customers and vendors) with linked accounts
- **Manual lettering** to match invoices with payments
- **Balance tracking** (amount due/received)

### Financial Reports
- **General Ledger** - line-by-line movements with running balance
- **Trial Balance** - account totals by period
- **Journal Report** - entries grouped by journal
- **VAT Summary** - collected and deductible VAT by rate
- **PDF export** for all reports using FPDF

### Administration
- **User management** with role-based access (Admin, Accountant, Viewer)
- **Period locking** to prevent modifications
- **Year-end closing** with carry-forward entries generation
- **Audit logging** for all major actions

## Tech Stack

| Component | Technology |
|-----------|------------|
| Backend | PHP 5.6 (procedural, no framework) |
| Database | MySQL 5.7 (MyISAM engine) |
| Frontend | HTML, CSS, jQuery 1.2.x |
| PDF Generation | FPDF library |
| Containerization | Docker & Docker Compose |

### Architecture Characteristics (Intentionally Legacy)

This application deliberately uses 2006-era patterns:
- Procedural PHP with no framework or ORM
- SQL queries inline in PHP pages
- Global database connection variable
- MD5 password hashing (legacy style)
- Session-based authentication
- Input escaping via `mysqli_real_escape_string` (no prepared statements)
- CSRF protection via session tokens

## Installation

### Prerequisites

- Docker and Docker Compose installed
- Ports 8080 (web) and 3306 (MySQL) available

### Quick Start

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd php-legacy-example
   ```

2. **Start the containers**
   ```bash
   docker-compose up -d
   ```

3. **Wait for initialization** (10-30 seconds)
   ```bash
   docker-compose logs -f
   ```
   Wait until you see that both services are running.

4. **Access the application**

   Open your browser and navigate to: http://localhost:8080

### Default Credentials

| Username | Password | Role |
|----------|----------|------|
| admin | admin123 | Administrator |
| comptable | comptable123 | Accountant |
| lecteur | lecteur123 | Viewer |

## Running the Application

### Starting the Services

```bash
# Start in background
docker-compose up -d

# Start with logs visible
docker-compose up
```

### Stopping the Services

```bash
# Stop containers
docker-compose down

# Stop and remove volumes (resets database)
docker-compose down -v
```

### Viewing Logs

```bash
# All services
docker-compose logs -f

# Web server only
docker-compose logs -f web

# Database only
docker-compose logs -f db
```

### Accessing the Database

```bash
# Connect via MySQL client
docker-compose exec db mysql -u compta -pcompta123 compta

# Or from host if MySQL client installed
mysql -h 127.0.0.1 -P 3306 -u compta -pcompta123 compta
```

## Project Structure

```
php-legacy-example/
├── www/                      # Web root (Apache DocumentRoot)
│   ├── index.php            # Dashboard
│   ├── login.php            # Authentication
│   ├── logout.php           # Logout handler
│   ├── header.php           # Navigation template
│   ├── footer.php           # Footer template
│   ├── lib/                 # Library files
│   │   ├── db.php          # Database connection
│   │   ├── auth.php        # Authentication functions
│   │   ├── utils.php       # Helper utilities
│   │   └── pdf/            # FPDF library
│   ├── modules/            # Application modules
│   │   ├── setup/          # Configuration (company, accounts, etc.)
│   │   ├── entries/        # Accounting entries
│   │   ├── bank/           # Bank reconciliation
│   │   ├── letters/        # Manual lettering
│   │   ├── reports/        # Financial reports
│   │   ├── close/          # Period/year closing
│   │   └── admin/          # User management
│   ├── assets/             # Static files (CSS, JS)
│   ├── uploads/            # User uploads
│   └── pdf/                # Generated PDFs
├── sql/                     # Database scripts
│   ├── 01_schema.sql       # Table definitions
│   └── 02_seed.sql         # Initial data
├── docker-compose.yml       # Docker services
├── Dockerfile              # PHP/Apache image
├── SPECS.md                # Functional specifications (French)
└── CLAUDE.md               # AI assistant guide
```

## Usage Guide

### Creating an Accounting Entry

1. Navigate to **Entries > New Entry**
2. Select a journal (Sales, Purchases, Bank, or Miscellaneous)
3. Enter the entry date and description
4. Add lines with accounts, amounts (debit/credit), and optional VAT
5. Ensure **total debits = total credits** (balance shown in real-time)
6. Click **Save** to keep as draft, or **Validate** to post

### Importing Bank Statements

1. Navigate to **Bank > Import Statement**
2. Select the bank account
3. Upload a CSV file with format: `date;label;amount;ref`
4. Review imported lines in **Bank > Reconcile**
5. Match statement lines with accounting entries

### Running Reports

1. Navigate to **Reports** menu
2. Select report type (Ledger, Balance, Journal, VAT)
3. Apply filters (period, account, journal)
4. View on screen or export to PDF

### Year-End Closing

1. Ensure all periods are locked (**Close > Lock Periods**)
2. Navigate to **Close > Year End**
3. Click **Generate Carry-Forward Entries**
4. Review the generated opening balance entry

## Configuration

### Environment Variables (docker-compose.yml)

| Variable | Default | Description |
|----------|---------|-------------|
| DB_HOST | db | Database hostname |
| DB_USER | compta | Database username |
| DB_PASS | compta123 | Database password |
| DB_NAME | compta | Database name |

### PHP Settings (Dockerfile)

- `short_open_tag = On` (legacy PHP support)
- `upload_max_filesize = 10M`
- `post_max_size = 10M`

## Database Schema

The application uses 14 tables:

| Table | Purpose |
|-------|---------|
| users | User accounts and authentication |
| company | Company settings (single record) |
| periods | Fiscal periods (monthly) |
| accounts | Chart of accounts |
| journals | Accounting journals |
| vat_rates | VAT rate configurations |
| third_parties | Customers and vendors |
| entries | Accounting entries (header) |
| entry_lines | Entry line items |
| attachments | Uploaded documents |
| bank_accounts | Bank account settings |
| bank_statements | Imported statement headers |
| bank_statement_lines | Statement line items |
| lettering_groups / lettering_items | Lettering data |
| audit_log | Action audit trail |

## Troubleshooting

### Container won't start
```bash
# Check for port conflicts
lsof -i :8080
lsof -i :3306

# View detailed logs
docker-compose logs
```

### Database connection errors
```bash
# Ensure database is ready
docker-compose exec db mysqladmin ping -u root -p

# Reset database
docker-compose down -v
docker-compose up -d
```

### Permission errors on uploads
```bash
# Fix permissions inside container
docker-compose exec web chown -R www-data:www-data /var/www/html/uploads
docker-compose exec web chown -R www-data:www-data /var/www/html/pdf
```

## License

This project is for educational purposes, demonstrating legacy PHP development patterns.
