import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable, map } from 'rxjs';

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

export interface Categoria {
  id_categoria: number;
  nombre: string;
  descripcion: string;
  activo: boolean;
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

export interface CarritoItem {
  id_detalle_carrito: number;
  id_producto: number;
  nombre: string;
  cantidad: number;
  precio_unitario: string | number;
  subtotal: string | number;
  existencia: number;
}

export interface Carrito {
  id_carrito: number | null;
  items: CarritoItem[];
  total: string | number;
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

export interface Direccion {
  id_direccion: number;
  nombre: string;
  calle: string;
  numero_exterior: string;
  numero_interior: string | null;
  colonia: string;
  codigo_postal: string;
  municipio: string;
  estado: string;
  pais: string;
  es_principal: boolean | string;
}

export interface DireccionDatos {
  nombre: string;
  calle: string;
  numero_exterior: string;
  numero_interior?: string | null;
  colonia: string;
  codigo_postal: string;
  municipio: string;
  estado?: string;
  id_estado?: number;
  es_principal?: boolean;
}

export interface AsentamientoCp {
  colonia: string;
  municipio: string;
  estado: string;
  codigo_postal: string;
}

export interface ColoniasResponse {
  codigo_postal: string;
  asentamientos: AsentamientoCp[];
}

export interface EstadoMx {
  id_estado: number;
  nombre: string;
}

export interface PedidoResumen {
  id_pedido: number;
  numero_pedido: string;
  fecha_pedido: string;
  total: string | number;
  moneda: string;
  estado: string;
}

export interface ItemRecibo {
  id_producto: number;
  identificador: string;
  nombre: string;
  cantidad: number;
  precio_unitario: string | number;
  subtotal: string | number;
  vendedor: string;
}

export interface Recibo {
  id_pedido: number;
  numero_pedido: string;
  fecha_pedido: string;
  total: string | number;
  moneda: string;
  estado: string;
  persona: string;
  direccion: {
    nombre: string;
    calle: string;
    numero_exterior: string;
    numero_interior: string | null;
    colonia: string;
    codigo_postal: string;
    municipio: string;
    estado: string;
    pais: string;
  };
  detalle: ItemRecibo[];
}

export interface Notificacion {
  id_notificacion: number;
  id_pedido: number | null;
  numero_pedido: string | null;
  estado_pedido: string | null;
  mensaje: string;
  leida: boolean | string | number;
  fecha_creacion: string;
}

export interface NotificacionesRespuesta {
  no_leidas: number;
  notificaciones: Notificacion[];
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

  categorias(): Observable<Categoria[]> {
    return this.http.get<Categoria[]>(`${API_URL}/categorias`);
  }

  filtrarProductos(filtros: {
    id_categoria?: string;
    precio_min?: string;
    precio_max?: string;
    nombre?: string;
    disponibilidad?: string;
    orden?: string;
  }): Observable<Producto[]> {
    const params: Record<string, string> = {};
    for (const [clave, valor] of Object.entries(filtros)) {
      if (valor !== undefined && valor !== null && valor !== '') {
        params[clave] = valor;
      }
    }
    return this.http.get<Producto[]>(`${API_URL}/productos`, { params });
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

  carrito(token: string): Observable<Carrito> {
    return this.http.get<Carrito>(`${API_URL}/carrito`, {
      headers: this.headers(token),
    });
  }

  vaciarCarrito(token: string): Observable<unknown> {
    return this.http.delete(`${API_URL}/carrito`, {
      headers: this.headers(token),
    });
  }

  eliminarItemDelCarrito(token: string, idProducto: number): Observable<unknown> {
    return this.http.delete(`${API_URL}/carrito/items/${idProducto}`, {
      headers: this.headers(token),
    });
  }

  modificarCantidad(
    token: string,
    idProducto: number,
    cantidad: number,
  ): Observable<unknown> {
    return this.http.patch(
      `${API_URL}/carrito/items/${idProducto}`,
      { cantidad },
      { headers: this.headers(token) },
    );
  }

  direcciones(token: string): Observable<Direccion[]> {
    return this.http.get<Direccion[]>(`${API_URL}/direcciones`, {
      headers: this.headers(token),
    });
  }

  crearDireccion(token: string, datos: DireccionDatos): Observable<Direccion> {
    return this.http.post<Direccion>(`${API_URL}/direcciones`, datos, {
      headers: this.headers(token),
    });
  }

  actualizarDireccion(
    token: string,
    idDireccion: number,
    datos: DireccionDatos,
  ): Observable<Direccion> {
    return this.http.put<Direccion>(
      `${API_URL}/direcciones/${idDireccion}`,
      datos,
      { headers: this.headers(token) },
    );
  }

  eliminarDireccion(token: string, idDireccion: number): Observable<unknown> {
    return this.http.delete(`${API_URL}/direcciones/${idDireccion}`, {
      headers: this.headers(token),
    });
  }

  establecerDireccionPrincipal(token: string, idDireccion: number): Observable<unknown> {
    return this.http.post(
      `${API_URL}/direcciones/${idDireccion}/principal`,
      {},
      { headers: this.headers(token) },
    );
  }

  coloniasPorCp(token: string, codigoPostal: string): Observable<AsentamientoCp[]> {
    return this.http
      .get<ColoniasResponse>(
        `${API_URL}/cp-mx/colonias`,
        {
          params: { codigo_postal: codigoPostal },
          headers: this.headers(token),
        },
      )
      .pipe(map((respuesta) => respuesta.asentamientos));
  }

  estados(): Observable<EstadoMx[]> {
    return this.http.get<EstadoMx[]>(`${API_URL}/estados-mexico`);
  }

  crearPedido(token: string, idDireccion: number): Observable<{ id_pedido: number }> {
    return this.http.post<{ id_pedido: number }>(
      `${API_URL}/pedidos`,
      { id_direccion: idDireccion },
      { headers: this.headers(token) },
    );
  }

  recibo(token: string, idPedido: number): Observable<Recibo> {
    return this.http.get<Recibo>(`${API_URL}/pedidos/${idPedido}`, {
      headers: this.headers(token),
    });
  }

  confirmarPago(token: string, idPedido: number): Observable<{ mensaje: string }> {
    return this.http.post<{ mensaje: string }>(
      `${API_URL}/pedidos/${idPedido}/confirmar`,
      {},
      { headers: this.headers(token) },
    );
  }

  cancelarPedido(token: string, idPedido: number): Observable<{ mensaje: string }> {
    return this.http.post<{ mensaje: string }>(
      `${API_URL}/pedidos/${idPedido}/cancelar`,
      {},
      { headers: this.headers(token) },
    );
  }

  misPedidos(token: string): Observable<PedidoResumen[]> {
    return this.http.get<PedidoResumen[]>(`${API_URL}/pedidos/mios`, {
      headers: this.headers(token),
    });
  }

  notificaciones(token: string): Observable<NotificacionesRespuesta> {
    return this.http.get<NotificacionesRespuesta>(`${API_URL}/notificaciones`, {
      headers: this.headers(token),
    });
  }

  marcarNotificacionesLeidas(token: string): Observable<{ mensaje: string }> {
    return this.http.post<{ mensaje: string }>(
      `${API_URL}/notificaciones/leer`,
      {},
      { headers: this.headers(token) },
    );
  }

  confirmarEntrega(token: string, idPedido: number): Observable<{ mensaje: string }> {
    return this.http.post<{ mensaje: string }>(
      `${API_URL}/pedidos/${idPedido}/confirmar-entrega`,
      {},
      { headers: this.headers(token) },
    );
  }

  private headers(token: string): HttpHeaders {
    return new HttpHeaders({ Authorization: `Bearer ${token}` });
  }
}
