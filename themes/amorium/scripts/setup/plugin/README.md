# AmoriumCms.Amorium

October CMS 4 plugin of the [Amorium theme](https://github.com/AmoriumCms/theme).

Requirement: An installed October CMS 4 site (PHP 8.2+, MySQL InnoDB).

## Features

Feature | What it does
------------- | -------------
Pages | Section-based page builder with SEO fields
Sections | Reusable section library with Twig HTML, YAML fields, scoped CSS and JavaScript, plus Font Picker and Remix Icon form widgets
Posts | Blog posts and categories
Navigation | Menu builder up to 4 levels deep
Forms | Forms built from sections and a submission inbox in the admin panel
Business | Business name, contact details, opening hours and social links for each site
Demo | Saves the site content as demo content and installs it with one click
Translate | AI translation with Google Gemini or a self-hosted Gemma translator, AI review and a translation memory

## Installation

Install the plugin together with the theme, from the October Marketplace or directly from these repositories:

```bash
php artisan plugin:install AmoriumCms.Amorium --from=https://github.com/AmoriumCms/plugin.git
php artisan theme:install AmoriumCms.Amorium --from=https://github.com/AmoriumCms/theme.git
php artisan october:migrate
php artisan tailor:migrate
php artisan theme:use amoriumcms-amorium
```

The full installation steps are in the [theme repository](https://github.com/AmoriumCms/theme).

## Automatic Translation

Add to the `.env` file:

```env
TRANSLATE_ENABLED=true
TRANSLATE_MODEL="gemini-flash-latest"
TRANSLATE_API_KEY=
```

To override the other settings, copy `config/translate.php` to `config/amoriumcms/amorium/translate.php` in the site root.

## Upgrading from the Separate Amorium Plugins

Earlier versions used eight plugins (`Amorium.Pages`, `Amorium.Sections`, `Amorium.Post`, `Amorium.Navigation`, `Amorium.Form`, `Amorium.Business`, `Amorium.Demo` and `Amorium.Translate`). Delete the `plugins/amorium` folder, install this plugin and run `php artisan october:migrate`. The content, form submissions, business information and translation memory are kept.

## Development

This repository is not edited by hand. The `themes/amorium/scripts/package.ps1` script in the development project writes the plugin to this repository, commits it and adds the version tag from `updates/version.yaml`.
