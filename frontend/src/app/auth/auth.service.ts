import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

const API_URL = 'http://127.0.0.1:8080/api';

export interface DatosRegistro {
  nombre_usuario: string;
  correo: string;
  contrasena: string;
  nombre: string;
  apellido_paterno: string;
  apellido_materno?: string;
  telefono?: string;
}

export interface RespuestaLogin {
  token: string;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);

  login(correo: string, contrasena: string): Observable<RespuestaLogin> {
    return this.http.post<RespuestaLogin>(`${API_URL}/auth/login`, {
      correo,
      contrasena,
    });
  }

  registrar(datos: DatosRegistro): Observable<unknown> {
    return this.http.post(`${API_URL}/auth/registro`, datos);
  }

  guardarToken(token: string): void {
    localStorage.setItem('shoptify_token', token);
  }

  obtenerToken(): string | null {
    return localStorage.getItem('shoptify_token');
  }

  eliminarToken(): void {
    localStorage.removeItem('shoptify_token');
  }

  rutaSegunRol(token: string): string {
    try {
      const payload = JSON.parse(
        atob(token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')),
      );
      return payload.rol === 'administrador' ? '/administrador' : '/comprador';
    } catch {
      return '/comprador';
    }
  }
}
