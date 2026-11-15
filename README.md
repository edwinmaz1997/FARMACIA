# FarmaPro — Sistema de farmacias

Plataforma multi-farmacia en PHP 7.4+ / MySQL con Tailwind (compilado) y JS vanilla.

## Instalación (una sola vez)
1. Crear la base de datos MySQL y su usuario en cPanel.
2. Abrir `https://farmacia.nuevaexpress.com/install.php`, ingresar los datos de la base y crear el superadministrador.
3. El instalador crea las tablas y `config/config.local.php` (no se sube por el deploy) y queda bloqueado.

## Deploy
Cada push a `main` compila el CSS y sube los archivos por FTPS (`.github/workflows/deploy.yml`).

## Estructura
- `index.php` — router (`?p=pagina`)
- `app/pages/admin` — panel de plataforma: farmacias, suscripciones, pagos, reportes
- `app/pages/farmacia` — POS, ventas, productos, lotes, inventario, clientes, proveedores, compras, gastos, reportes, usuarios
- `database/schema.sql` — esquema
- CSS: `npx tailwindcss@3 -i assets/src/app.css -o assets/app.css --minify`
