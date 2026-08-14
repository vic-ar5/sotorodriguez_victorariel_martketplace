import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable } from 'rxjs';

const API_URL = 'http://127.0.0.1:8080/api';

export interface Producto {
  id_producto: number;
  nombre: string;
  descripcion: string;
  precio: string | number;
  existencia: number;
  moneda: string;
  id_vendedor: number;
  categoria: string;
  imagen: string | null;
}

export interface DetalleProducto {
  id_producto: number;
  identificador: string;
  nombre: string;
  descripcion: string;
  precio: string | number;
  existencia: number;
  moneda: string;
  estado: string;
  fecha_registro: string;
  categoria: string;
  vendedor: string;
  nombre_vendedor: string;
  imagenes: { url_publica: string }[];
}

export interface MiPerfil {
  id_usuario: number;
  nombre_usuario: string;
  correo: string;
  rol: string;
  nombre: string;
  apellido_paterno: string;
  apellido_materno: string | null;
  telefono: string | null;
}

@Injectable({ providedIn: 'root' })
export class CompradorService {
  private readonly http = inject(HttpClient);

  productos(): Observable<Producto[]> {
    return this.http.get<Producto[]>(`${API_URL}/productos`);
  }

  buscarProductos(nombre: string): Observable<Producto[]> {
    return this.http.get<Producto[]>(`${API_URL}/productos`, {
      params: { nombre },
    });
  }

  detalleProducto(id: number): Observable<DetalleProducto> {
    return this.http.get<DetalleProducto>(`${API_URL}/productos/${id}`);
  }

  miPerfil(token: string): Observable<MiPerfil> {
    return this.http.get<MiPerfil>(`${API_URL}/usuarios/mi-perfil`, {
      headers: this.headers(token),
    });
  }

  actualizarMiPerfil(token: string, datos: Partial<MiPerfil>): Observable<unknown> {
    return this.http.put(`${API_URL}/usuarios/mi-perfil`, datos, {
      headers: this.headers(token),
    });
  }

  agregarAlCarrito(
    token: string,
    idProducto: number,
    cantidad = 1,
  ): Observable<unknown> {
    return this.http.post(
      `${API_URL}/carrito/items`,
      { id_producto: idProducto, cantidad },
      { headers: this.headers(token) },
    );
  }

  private headers(token: string): HttpHeaders {
    return new HttpHeaders({ Authorization: `Bearer ${token}` });
  }
}
