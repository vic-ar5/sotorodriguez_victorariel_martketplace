# Shoptify

## 1. Nombre del proyecto
Shoptify

## 2. Descripción
Shoptify es un marketplace digital desarrollado para la venta de productos en línea, con una experiencia de compra clara para clientes y un panel administrativo para gestionar categorías, productos, pedidos y usuarios.

La aplicación permite a los compradores explorar el catálogo, filtrar productos, agregar artículos al carrito, generar pedidos, gestionar direcciones y confirmar entregas. Por su parte, el administrador puede crear y administrar categorías, productos, usuarios y pedidos desde un panel específico.

## 3. Objetivo
El objetivo del proyecto es simular un marketplace funcional con arquitectura modular, uso de base de datos relacional, autenticación con JWT y almacenamiento de imágenes en Google Drive, brindando una solución completa para gestión de ventas en línea.

## 4. Tecnologías utilizadas
- PHP 8.2
- Flight PHP
- PostgreSQL
- Angular 21
- TypeScript
- JWT para autenticación
- Google Drive API
- HTML, CSS y Bootstrap/Tailwind-like styling
- Composer
- npm

## 5. Requisitos
Antes de ejecutar el proyecto, asegúrate de contar con:
- PHP 8.2 o superior
- Composer
- PostgreSQL 14 o superior
- Node.js 20 o superior
- npm
- Acceso a Google Cloud Console con Drive API habilitada
- Navegador moderno

## 6. Instalación
### Backend
```bash
cd backend
composer install
```

### Frontend
```bash
cd frontend
npm install
```

## 7. Ejecutar los servidores
### Backend
```bash
cd backend
php -S localhost:8000 -t public
```

### Frontend
```bash
cd frontend
npm start
```

> El backend queda disponible en `http://localhost:8000` y el frontend en `http://localhost:4200`.

## 8. Configuración
Crea un archivo `.env` en la raíz del proyecto con la siguiente estructura:

```env
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_NAME=marketplace
DB_USER=postgres
DB_PASS=tu_password

JWT_SECRET=tu_secreto_largo_y_seguro
JWT_TTL=3600

GOOGLE_DRIVE_CLIENT_ID=tu_client_id
GOOGLE_DRIVE_CLIENT_SECRET=tu_client_secret
GOOGLE_DRIVE_REFRESH_TOKEN=tu_refresh_token
GOOGLE_DRIVE_ROOT_FOLDER_ID=tu_carpeta_raiz_drive
```

> El archivo real del proyecto se encuentra en la raíz del repositorio y debe configurarse con tus credenciales locales.

## 8. Configuración de PostgreSQL
1. Crea la base de datos:
```bash
createdb -U postgres marketplace
```

2. Ejecuta el esquema:
```bash
psql -U postgres -d marketplace -f database/schema.sql
```

3. Carga los datos semilla:
```bash
psql -U postgres -d marketplace -f database/semillas.sql
```

4. Opcionalmente, ejecuta el catálogo de códigos postales:
```bash
psql -U postgres -d marketplace -f database/codigos_postales.sql
```

La base de datos se configura por defecto con:
- host: localhost
- puerto: 5432
- base de datos: marketplace
- usuario: postgres

Más detalles en [database/README.md](database/README.md).

## 9. Configuración de Google Drive
Para que la subida de imágenes funcione correctamente:

1. Crear un proyecto en Google Cloud Console.
2. Habilitar la API de Google Drive.
3. Configurar credenciales OAuth 2.0.
4. Generar un refresh token válido para Drive con el alcance `https://www.googleapis.com/auth/drive.file`.
5. Crear una carpeta raíz en Google Drive, por ejemplo: `Marketplace-Mexico`.
6. Copiar el ID de la carpeta raíz y colocarlo en `GOOGLE_DRIVE_ROOT_FOLDER_ID`.

El proyecto usa Google Drive para almacenar imágenes y guardar solo metadatos en la base de datos.

## 10. Usuarios de prueba
Los usuarios configurados en las semillas del proyecto son:

### Administrador
- Usuario: `admin`
- Correo: `admin@marketplace.mx`
- Contraseña: `password`

### Compradores
- Usuario: `maria`
- Correo: `maria@correo.mx`
- Contraseña: `password`

- Usuario: `jose`
- Correo: `jose@correo.mx`
- Contraseña: `password`

- Usuario: `ana`
- Correo: `ana@correo.mx`
- Contraseña: `password`

## 11. Funcionalidades
### Comprador
- Registro e inicio de sesión
- Recuperación del perfil
- Exploración del catálogo de productos
- Búsqueda y filtros avanzados
- Carrito de compras
- Gestión de direcciones de entrega
- Generación de pedidos
- Confirmación de entrega
- Notificaciones del sistema
- Descarga de recibos

### Administrador
- Inicio de panel administrativo
- Gestión de categorías
- Gestión de productos
- Subida de imágenes a Google Drive
- Gestión de usuarios
- Gestión de pedidos
- Cambio de estados de pedidos y categorías
- Dashboard con métricas del sistema

> Agrega las imágenes reales en una carpeta `capturas/` para documentar el proyecto visualmente.

## 13. URL de implementación
Actualmente no se reporta una URL pública de despliegue en este repositorio. Si se despliega en producción o en un hosting, se puede actualizar esta sección con la URL final.

Ejemplo:
```md
https://shoptify.example.com
```

## 14. Estructura general del proyecto
- [backend](backend)
- [database](database)
- [frontend](frontend)
- [Bruno](Bruno)

## 15. Documentación adicional
- [database/README.md](database/README.md)
- [frontend/README.md](frontend/README.md)

## 16. Licencia
Este proyecto se entrega como solución académica y de desarrollo para el marketplace propuesto.
