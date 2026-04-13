# Panth Advanced Contact Us for Magento 2

Replace the default Magento 2 contact form with a modern, feature-rich contact page. Includes custom fields, 3-layer bot protection, admin submission management, and transactional email notifications. Built for Hyva themes.

## Features

- **Modern Contact Form** — Alpine.js-powered form with real-time validation
- **Custom Fields** — unlimited custom fields (text, textarea, select, radio, checkbox, email, tel)
- **Bot Protection** — honeypot field, time-based validation, IP rate limiting
- **Submission Management** — admin grid with status tracking (New/Read/Replied)
- **Email Notifications** — admin notification and optional customer confirmation emails
- **Contact Info Sidebar** — display email, phone, address, and business hours
- **Logged-in Pre-fill** — auto-populates name and email for logged-in customers

## Requirements

- PHP 8.1 or higher
- Magento 2.4.4 or higher
- Panth_Core module

## Installation

```bash
composer require mage2kishan/module-advanced-contact-us
bin/magento module:enable Panth_AdvancedContactUs
bin/magento setup:upgrade
bin/magento cache:flush
```

## Configuration

Navigate to **Stores > Configuration > Panth Extensions > Advanced Contact Us**.

## Support

- Website: https://kishansavaliya.com
- Email: kishansavaliyakb@gmail.com

## License

Proprietary - see LICENSE.txt
