import { inject } from '@angular/core';
import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

const CLAVE_TOKEN = 'shoptify_token';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      if (error.status === 401 && !req.url.includes('/auth/login')) {
        localStorage.removeItem(CLAVE_TOKEN);
        if (router.url !== '/login') {
          router.navigate(['/login']);
        }
      }
      return throwError(() => error);
    }),
  );
};
