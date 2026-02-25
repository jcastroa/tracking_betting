# Betting Tracker

Sistema de gestión de tickets de apuestas deportivas dockerizado con PHP 8.2, MariaDB y Bootstrap 5.

## Tecnologías

- **PHP 8.2** (FPM) + **Nginx**
- **MariaDB 10.11**
- **Bootstrap 5.3** + Bootstrap Icons
- **DataTables** para la tabla interactiva
- **PhpSpreadsheet** para exportación a Excel

## Inicio rápido

```bash
docker compose up -d --build
```

Acceder en: http://localhost:8080

## Funcionalidades

- Importar análisis desde JSON (pegar en el formulario)
- Tabla de tickets con filtros (confianza, riesgo, resultado, fecha)
- Editar cualquier campo de un ticket (incluido resultado y ganancia)
- Eliminar tickets individuales
- Exportar a Excel (.xlsx) con hoja de resumen y estadísticas
- Panel de estadísticas en tiempo real (total, ganados, perdidos, ganancia neta)

## Estructura

```
├── docker-compose.yml
├── Dockerfile
├── nginx/default.conf
├── mysql/init.sql          # Schema inicial de la BD
└── src/
    ├── composer.json
    ├── config/database.php
    └── public/
        ├── index.php       # Página principal
        ├── process.php     # Procesamiento del JSON
        ├── get_ticket.php  # API AJAX para edición
        ├── edit.php        # Guardar cambios de ticket
        ├── delete.php      # Eliminar ticket
        └── export.php      # Exportar a Excel
```

## Puertos

| Servicio | Puerto |
|----------|--------|
| Web app  | 8080   |
| MariaDB  | 3307   |
