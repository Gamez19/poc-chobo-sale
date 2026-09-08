# Cacao Control

Sistema en español para administrar inventario, producción por lotes y ventas. Está construido con Laravel 13, Livewire 4, Tailwind CSS 4 y SQLite.

## Funcionalidad

- Panel con ventas del día, existencias y alertas de insumos.
- Materias primas con entradas, costo promedio ponderado, unidad de medida y nivel mínimo.
- Productos con variantes, precio y receta independientes.
- Producción por lotes que consume automáticamente las materias primas de las recetas.
- Registro de ventas con asignación FIFO al lote más antiguo disponible.
- Precio histórico guardado en cada venta, aunque luego cambie el precio de la variante.
- Reportes filtrables por fecha y lote, con ingresos y unidades por categoría.
- Datos iniciales para Chocobanano Simple, Maní y Chispitas.

## Requisitos

- PHP 8.3 o superior con PDO SQLite
- Composer 2
- Node.js 20 o superior

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Abre `http://localhost:8000`.

Para reiniciar la base con los datos de demostración:

```bash
php artisan migrate:fresh --seed
```

## Verificación

```bash
php artisan test
./vendor/bin/pint --test
npm run build
```

## Diseño técnico

La lógica crítica vive en servicios transaccionales para mantener los componentes Livewire pequeños y evitar duplicación:

- `ProductionService`: valida recetas y stock, crea lotes y registra consumo de materias primas.
- `SalesService`: valida existencias, asigna unidades por FIFO y conserva precios históricos.
- `RawMaterialStockService`: registra entradas y recalcula el costo promedio ponderado.

Los importes monetarios se almacenan como centavos enteros para evitar errores de precisión. Las ventas se relacionan con los lotes mediante asignaciones explícitas, lo que permite reportes confiables por fecha y lote.

