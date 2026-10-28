# Download Guard

WordPress plugin that stops members from draining your downloads. Works with **[Download Monitor](https://wordpress.org/plugins/download-monitor/)**, **[Easy Digital Downloads](https://wordpress.org/plugins/easy-digital-downloads/)** 3 and **[WooCommerce](https://wordpress.org/plugins/woocommerce/)** downloadable products.

A common abuse on membership and download sites: someone subscribes to the cheapest plan, downloads the whole library in a few minutes and cancels. Download Guard stops it.

## What it does

- **Daily and 30-day limits at the same time**, per role, with **rolling windows** (last 24 hours and last 30 days), so nobody gets a fresh quota at midnight or on the first of the month.
- **Stricter limits for new members** during a period you choose. With Restrict Content Pro, the period starts with the first membership.
- **A clear message** when a limit is reached, telling the member when they can download again.
- **Bulk-download alerts**: an email when an account downloads too much too fast, with an optional automatic block until you review it.
- **Activity screen**: top downloaders in the last 24 hours and 30 days, and blocked accounts with one-click block and unblock.
- **Sign-up filter**: rejects usernames made only of numbers, disposable email addresses and email domains that cannot receive mail, on the WordPress, Restrict Content Pro, Easy Digital Downloads and WooCommerce registration forms.

Downloads are read from each plugin's own download log, so no extra table is created, and downloads from all of them are added up for each account. Administrators are never limited.

## Requirements

- WordPress 6.5 or later
- PHP 8.0 or later
- Download Monitor, Easy Digital Downloads 3 or WooCommerce

## Installation

1. Copy the plugin folder to `wp-content/plugins/download-guard/` (or upload the ZIP from **Plugins → Add New**).
2. Activate it.
3. Go to **Download Guard → Settings** and set the limits for each role.

## Developers

Filters and actions are listed in [`readme.txt`](readme.txt). Support for another download plugin can be added with the `dlgd_adapter_classes` filter and a class that extends `DLGD_Adapter`.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
