# Local Development Environment

This file contains machine-specific settings for the ekklesiaONLINE local development environment.

## Local Domain

- Primary local domain: `ekklesiaonline.local`
- `www.ekklesiaonline.local` is also configured as a ServerAlias.
- The old `ekklesiard.local` domain has been retired and removed from `/etc/hosts` and Apache virtual hosts.

## Web Server

- Homebrew `httpd` (Apache) runs on port 80.
- PHP is loaded as an Apache module from `/opt/homebrew/opt/php/lib/httpd/modules/libphp.so`.
- Document root: `/Users/juanrosariodelacruz/Sites/ekklesiaONLINE/public`
- Virtual host config: `/opt/homebrew/etc/httpd/extra/httpd-vhosts.conf`
- Main Apache config: `/opt/homebrew/etc/httpd/httpd.conf`

## Application Config

- `app.baseURL` in `.env`: `http://ekklesiaonline.local/`
