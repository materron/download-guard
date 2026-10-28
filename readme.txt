=== Download Guard ===
Contributors: materron
Tags: download limit, download monitor, easy digital downloads, woocommerce, membership
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stop members from draining your downloads: daily and 30-day limits, stricter limits for new members and bulk-download alerts.

== Description ==

A common abuse on membership and download sites: someone subscribes to the cheapest plan, downloads your whole library in a few minutes and cancels. Download Guard stops it.

Works with **Download Monitor**, **Easy Digital Downloads 3** and **WooCommerce** downloadable products, with any membership plugin that assigns roles. Downloads from all of them are added up for each account.

= Download limits =

* **A daily limit and a 30-day limit at the same time**, per role. Both must be respected.
* **Rolling windows** (last 24 hours and last 30 days), so nobody gets a fresh quota by waiting for midnight or the first of the month.
* **Stricter limits for new members** during a period you choose (30 days by default). This is what stops "subscribe, download everything, cancel". With Restrict Content Pro, the new-member period starts with the first membership that was ever activated.
* **A clear message** when a limit is reached, telling the member when they can download again, instead of a generic "no access" page.

= Bulk-download alerts =

* Get an **email when an account downloads too much too fast** (by default, more than 20 downloads within 10 minutes).
* Optionally **block the account** from downloading until you review it.

= Activity screen =

* **Top downloaders** in the last 24 hours and the last 30 days, with each member's status.
* **Blocked accounts**, with one-click block and unblock.

= Sign-up filter =

Bulk downloaders usually sign up with throwaway accounts. Download Guard can reject, on the WordPress, Restrict Content Pro, Easy Digital Downloads and WooCommerce registration forms:

* usernames made only of numbers,
* disposable email addresses,
* email domains that cannot receive mail (DNS check).

= How it counts =

Downloads are read from the download plugin's own log, so no extra table is created and existing history counts from day one. Administrators are never limited.

This plugin is not affiliated with or endorsed by the Download Monitor, Easy Digital Downloads or WooCommerce teams.

= Privacy =

Download Guard does not send data to any external service and does not track anyone. It reads the download logs that Download Monitor, Easy Digital Downloads and WooCommerce already keep, and stores only its settings and, for blocked accounts, when and why they were blocked (in the user's meta data).

Bulk-download alerts are emailed from your site to the address you choose, with the account's username and email address.

The optional sign-up check "Email domains that cannot receive mail" makes a DNS lookup of the email address's domain through your server's DNS resolver. No other data is sent. You can turn it off in the settings.

= Developers =

Development happens on GitHub: [github.com/materron/download-guard](https://github.com/materron/download-guard).


Filters:

* `dlgd_user_limits` — change the limits for a user.
* `dlgd_member_since` — return when a user became a member (for membership plugins other than Restrict Content Pro).
* `dlgd_is_exempt` — exempt users from limits and alerts (administrators by default).
* `dlgd_denied_message` — change the message shown when a download is denied.
* `dlgd_signup_rejection` — change why a sign-up is rejected.
* `dlgd_disposable_email_domains` — change the list of disposable email domains.
* `dlgd_adapter_classes` — add support for another download plugin (extend `DLGD_Adapter`).

Actions:

* `dlgd_download_denied` — a download was denied.
* `dlgd_burst_detected` — an account downloaded too much too fast.
* `dlgd_account_blocked` and `dlgd_account_unblocked`.

== Installation ==

1. Install and activate Download Monitor, Easy Digital Downloads 3 or WooCommerce.
2. Install and activate Download Guard.
3. Go to **Download Guard → Settings** and set the limits for each role.

== Frequently Asked Questions ==

= Does it work with Advanced Access Manager or my membership plugin? =

Yes. Download Guard runs after the other access checks and only ever denies downloads that would otherwise be allowed. Use your membership plugin to decide who can download, and Download Guard to decide how much.

= What if a user has several roles? =

The most generous limit among their limited roles applies. Roles without any limit configured are ignored.

= Which downloads count? =

With Download Monitor, downloads logged as completed or redirected. With Easy Digital Downloads and WooCommerce, every file download in their logs, matched to the account that placed the order (download links work without logging in). Downloads from guest orders are not limited.

= I use more than one of these plugins. =

Downloads from all of them are added up for each account.

= I disabled Download Monitor's logging. =

Then its downloads cannot be counted and no limit applies to them. The settings screen warns you about it.

= What happens when I unblock an account? =

It can download again right away. Bulk-download detection only counts downloads made after the unblock, so the downloads that caused the block don't block it again.

== Screenshots ==

1. Limits per role, with separate limits for new members.
2. Bulk-download detection and sign-up filter.
3. Top downloaders and blocked accounts.

== Changelog ==

= 1.0.0 =
* First release.
