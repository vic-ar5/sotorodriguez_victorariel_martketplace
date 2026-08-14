import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    pathMatch: 'full',
    redirectTo: 'login',
  },
  {
    path: 'login',
    loadComponent: () => import('./auth/login/login').then((m) => m.Login),
  },
  {
    path: 'registro',
    loadComponent: () =>
      import('./auth/registro/registro').then((m) => m.Registro),
  },
  {
    path: 'comprador',
    loadChildren: () =>
      import('./client/comprador/panel/panel.routes').then(
        (m) => m.COMPRADOR_ROUTES,
      ),
  },
  {
    path: 'administrador',
    loadChildren: () =>
      import('./client/administrador/panel/panel.routes').then(
        (m) => m.ADMINISTRADOR_ROUTES,
      ),
  },
];
