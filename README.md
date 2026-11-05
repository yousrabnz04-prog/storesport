# StoreSport

An online sports-equipment store built with **PHP, MySQL and Bootstrap**. Customers browse a catalogue, fill a cart, place cash-on-delivery orders and chat with the shop's support team. Administrators manage products, categories, orders, customers and other administrators from a dedicated back-office.

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Project Structure](#project-structure)
- [Database](#database)
- [Security](#security)
- [Roadmap](#roadmap)
- [Contributing](#contributing)
- [License](#license)

## Features

**Storefront**
- Home page with categories, featured products and new arrivals
- Catalogue with category filter, search, sorting and pagination
- Product pages with stock indicator and related products
- Shopping cart with AJAX "add to cart" and live cart counter
- Checkout with server-side stock validation (cash on delivery)
- Order history, order details and cancellation of pending orders (stock is restored)
- Customer accounts: registration, login, profile and password change

**Support chat**
- One private conversation per customer; any administrator can reply
- Image / PDF / text attachments, delivered through an access-checked download endpoint
- Unread counters and automatic refresh

**Administration**
- Dashboard: customers, products, orders, revenue, pending orders and low-stock alerts
- Product management with image upload, featured flag and visibility
- Category management, order processing (order and payment status), customer suspension
- Administrator accounts with two levels (**owner** and **admin**), an optional "manage administrators" permission, ownership transfer and an activity log

## Tech Stack

| Layer      | Technology                                          |
|------------|-----------------------------------------------------|
| Backend    | PHP 8.0+ (PDO with prepared statements)             |
| Database   | MySQL 5.7+ / MariaDB 10.4+ (InnoDB, `utf8mb4`)      |
| Frontend   | Bootstrap 5.3, Font Awesome 6, vanilla JavaScript   |
| Web server | Apache 2.4 (`mod_headers`; `.htaccess` is provided) |

## Prerequisites

- PHP 8.0 or later with the `pdo_mysql`, `mbstring` and `fileinfo` extensions
- MySQL or MariaDB
- Apache 2.4 with `AllowOverride All` for the project folder
- Git

The quickest local setup is [XAMPP](https://www.apachefriends.org/), [WAMP](https://www.wampserver.com/) or [Laragon](https://laragon.org/).

## Installation

```bash
# 1. Get the code into your web root (e.g. htdocs for XAMPP)
git clone https://github.com/<your-username>/storesport.git
cd storesport

# 2. Create the database and tables
mysql -u root -p < database/schema.sql

# 3. (Optional) load a starter catalogue
mysql -u root -p storesport < database/seed.sql

# 4. Create your local configuration
cp config/config.example.php config/config.php
#    ...then edit config/config.php (database credentials, BASE_PATH)

# 5. Create the first administrator (becomes the owner)
php bin/create-admin.php your-username you@example.com
```

Make sure the web-server user can write to `uploads/products/` and `uploads/messages/`, then open `http://localhost/storesport/`. Administrators sign in on the regular login page and are redirected to `/admin/`.

> No default account or password is shipped with the project. Create your administrator with `bin/create-admin.php`.

## Configuration

Everything lives in `config/config.php` (copied from `config/config.example.php`, never committed):

| Constant | Description | Example |
|---|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | Database connection (environment variables of the same name take precedence) | `localhost`, `storesport` |
| `BASE_PATH` | URL path of the app, no trailing slash. `''` at the domain root | `/storesport` |
| `SITE_NAME`, `SITE_TAGLINE` | Branding | `StoreSport` |
| `CONTACT_EMAIL`, `CONTACT_PHONE`, `CONTACT_ADDRESS` | Shown in the footer when not empty | |
| `APP_TIMEZONE` | PHP timezone | `UTC` |
| `APP_DEBUG` | Show PHP/DB errors (development only) | `false` |

Use a dedicated MySQL user with limited privileges rather than `root`.

## Usage

**Customers**
1. Browse `/shop.php`, filter by category or search.
2. Click **Add to cart**. You are asked to sign in or register if needed.
3. Review the cart, then **Proceed to checkout** and confirm the order.
4. Follow your orders in **My orders** and write to the team in **Support**.

**Administrators**
- `/admin/` dashboard with key figures
- **Products** to add, edit, hide or delete items (JPG, PNG, WebP or GIF up to 5 MB)
- **Orders** to move an order through *pending → processing → shipped → delivered* (cancelling restores stock)
- **Messages** to answer customers
- **Administrators** (owner or permitted admins) to add colleagues and transfer ownership

Adding more administrators from the command line:

```bash
php bin/create-admin.php jane jane@example.com
```

## Project Structure

```
storesport/
├── index.php, shop.php, product.php        # Catalogue pages
├── cart.php, checkout.php                  # Cart and ordering
├── order-confirmation.php, orders.php      # Order details and history
├── login.php, register.php, logout.php     # Authentication
├── profile.php                             # Customer profile and password
├── messages.php, download.php              # Support chat and protected attachments
├── admin/                                  # Back-office (dashboard, products, categories,
│                                           #   orders, users, messages, admins)
├── api/messages-poll.php                   # JSON endpoint used by the chat
├── includes/
│   ├── bootstrap.php                       # Config, session, PDO, helpers
│   ├── functions.php                       # Auth, CSRF, uploads, cart, orders, messaging
│   ├── header.php / footer.php             # Storefront layout
│   ├── admin_header.php / admin_footer.php # Admin layout
│   ├── product-card.php, chat.php          # Reusable partials
├── config/config.example.php               # Configuration template
├── database/schema.sql, seed.sql           # Schema and optional starter data
├── bin/create-admin.php                    # CLI tool to create administrators
├── assets/                                 # CSS, JavaScript, placeholder image
├── uploads/                                # Product images and chat attachments (not tracked)
├── .github/workflows/php-lint.yml          # CI: PHP syntax check
└── .htaccess                               # Security headers, hidden files, no directory listing
```

## Database

| Table | Purpose |
|---|---|
| `admins` | Back-office accounts (`owner` / `admin`, permissions, last sign-in) |
| `users` | Customer accounts (`active` / `banned`) |
| `categories`, `products` | Catalogue |
| `cart` | Items in a customer's cart (unique per customer and product) |
| `orders`, `order_items` | Orders with a snapshot of names and prices at purchase time |
| `messages` | Support conversations (one thread per customer) |
| `admin_logs` | Audit trail of administrative actions |

## Security

- PDO prepared statements everywhere; output escaped with `e()`
- CSRF tokens on every state-changing request; sign-out and deletions are POST-only
- Passwords hashed with `password_hash()`; session ID regenerated at login; cookies are `HttpOnly` and `SameSite=Lax`
- Uploads validated by real MIME type, stored under random names, with script execution disabled in `uploads/`
- Chat attachments are never directly accessible; they go through `download.php`
- Administrator permissions are read from the database on every request
- Security headers and a Content-Security-Policy are set in `.htaccess`

Before going to production: serve the site over HTTPS, use a least-privilege database user, keep `APP_DEBUG` off, add server-level rate limiting for the login form, and keep `config/config.php` out of version control.

Please report vulnerabilities privately to the repository owner instead of opening a public issue.

## Roadmap

- Online payments (card / PayPal)
- E-mail notifications (order confirmation, status changes)
- Password reset by e-mail
- Product images gallery and variants (size, colour)
- Automated tests (PHPUnit) and Docker setup

## Contributing

1. Fork the repository
2. Create a branch: `git checkout -b feature/my-feature`
3. Commit your changes: `git commit -m "Add my feature"`
4. Push the branch and open a Pull Request

## License

Released under the [MIT License](LICENSE).
