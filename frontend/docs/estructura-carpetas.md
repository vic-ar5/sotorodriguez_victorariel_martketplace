# Estructura de carpetas del Frontend

Diagrama de la arquitectura `client/server` del frontend Angular, con el propósito de cada carpeta y las dependencias entre ellas.

## Diagrama general

```
frontend/
│
├── public/                         # Recursos estáticos servidos tal cual (favicon, etc.)
├── src/
│   ├── main.ts                     # Punto de entrada de la app (bootstrap)
│   ├── styles.css                  # Estilos globales (Tailwind)
│   ├── index.html                  # HTML raíz que monta <app-root>
│   │
│   └── app/
│       ├── app.ts                  # Componente raíz (selector <app-root>)
│       ├── app.html                # Plantilla raíz → <router-outlet />
│       ├── app.config.ts           # Proveedores globales (router, HttpClient)
│       ├── app.routes.ts           # Rutas principales (lazy-load por rol)
│       ├── app.spec.ts             # Test del componente raíz
│       │
│       ├── client/                 # CAPA DE PRESENTACIÓN (UI)
│       │   ├── comprador/          #   → Paneles del usuario COMPRADOR
│       │   │   └── panel/          #     → Dashboard/panel del comprador
│       │   └── administrador/      #   → Paneles del usuario ADMINISTRADOR
│       │       └── panel/          #     → Dashboard/panel del administrador
│       │
│       └── server/                 # CAPA DE DATOS (comunicación con el backend)
│           ├── modelos/            #   → Interfaces/tipos de datos
│           └── servicios/          #   → Servicios HTTP (llamadas a la API)
│
├── angular.json                    # Configuración del proyecto Angular
├── tsconfig*.json                  # Configuración de TypeScript
└── package.json                    # Dependencias y scripts npm
```

## Capa `client/` — Presentación

Todo lo que el usuario ve. Contiene **una carpeta por rol de usuario**, y dentro de cada rol un **panel** (dashboard).

```
client/
├── comprador/
│   └── panel/
│       ├── panel.ts            # Componente standalone (selector app-comprador-panel)
│       ├── panel.html          # Plantilla del panel del comprador
│       ├── panel.css           # Estilos del panel
│       ├── panel.routes.ts     # Rutas hijas del módulo comprador (COMPRADOR_ROUTES)
│       └── panel.spec.ts       # Tests del panel
└── administrador/
    └── panel/
        ├── panel.ts            # Componente standalone (selector app-administrador-panel)
        ├── panel.html
        ├── panel.css
        ├── panel.routes.ts     # Rutas hijas del módulo administrador (ADMINISTRADOR_ROUTES)
        └── panel.spec.ts
```

| Carpeta | Para qué sirve |
|---|---|
| `client/` | Capa de presentación: agrupa las vistas organizadas por rol de usuario. |
| `client/comprador/` | Vistas exclusivas del **comprador** (su panel personal). |
| `client/comprador/panel/` | El dashboard del comprador: componentes, plantilla, estilos y su ruteo propio. |
| `client/administrador/` | Vistas exclusivas del **administrador** (gestión del marketplace). |
| `client/administrador/panel/` | El dashboard del administrador: componentes, plantilla, estilos y su ruteo propio. |

## Capa `server/` — Datos

Todo lo que habla con el backend (API). Aquí no hay UI; son tipos y servicios inyectables.

```
server/
├── modelos/
│   └── producto.model.ts      # Interface Producto (contrato con la respuesta de la API)
└── servicios/
    └── producto.service.ts    # Servicio inyectable con métodos HTTP (listar, obtener, crear, actualizar, eliminar)
```

| Carpeta | Para qué sirve |
|---|---|
| `server/` | Capa de datos: comunicación con el backend y definición de los modelos. |
| `server/modelos/` | Interfaces TypeScript que describen la forma de los datos (ej. `Producto`). |
| `server/servicios/` | Servicios Angular (`@Injectable`) con `HttpClient` para llamar a los endpoints REST. |

## ¿Qué carpeta llama a cuál?

```
main.ts
   └─> bootstrap(App)                      app.ts
                                            └─> app.html  <router-outlet />
                                                  ▲
app.routes.ts ───carga perezosa (loadChildren)──┘
   ├─> client/comprador/panel/panel.routes.ts ──> CompradorPanel
   └─> client/administrador/panel/panel.routes.ts ──> AdministradorPanel

client/**/panel (componentes) ──usa──> server/servicios/*.service.ts
server/servicios/*.service.ts ──usa──> server/modelos/*.model.ts  (tipos)
```

### Flujo explicado

1. `main.ts` arranca la app y monta el componente raíz `app.ts`.
2. `app.ts` renderiza `app.html`, que solo contiene el `<router-outlet />`.
3. `app.routes.ts` define las rutas públicas: `/comprador` y `/administrador`. Usan `loadChildren` (**lazy loading**), así cada panel solo se carga cuando se navega a él.
4. `panel.routes.ts` de cada rol resuelve su ruta al componente del panel.
5. Cuando un panel necesita datos, **inyecta** un servicio de `server/servicios/` (ej. `ProductoService`).
6. El servicio usa `HttpClient` y tipa sus respuestas con las interfaces de `server/modelos/`.

## Reglas de dependencia

- `client/` **nunca** debe importar de otra carpeta de `client/` de otro rol.
- `client/` **sí** importa de `server/` (servicios y modelos).
- `server/` no importa de `client/` (es la capa inferior).
- Los archivos raíz (`app.*`, `app.routes.ts`) solo se encargan de orquestar rutas y proveedores.
