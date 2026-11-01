# Vendored flag icons

SVG flag icons from [flag-icons](https://github.com/lipis/flag-icons) v7.6.0 (MIT License), `flags/4x3/<cc>.svg`.
Shipped inside the package and served by the `flags/(:segment)` route in `src/Config/Routes.php`, so nothing is
fetched from a CDN at runtime. ISO 3166-1 alpha-2 country codes, mapped from locale codes in
`src/Views/ui/_topbar.php`'s `$flag()` map; add more by downloading
`https://cdn.jsdelivr.net/npm/flag-icons@<version>/flags/4x3/<cc>.svg` and adding the mapping there.
