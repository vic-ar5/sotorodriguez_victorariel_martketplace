import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable } from 'rxjs';

const API_URL = 'http://localhost:8000/api';

export interface ResumenProductos {
  total: number;
  disponibles: number;
  no_disponibles: number;
  por_categoria: {
    categoria: string;
    total: number;
    disponibles: number;
  }[];
}

export interface PedidosPorEstado {
  estado: string;
  cantidad: number;
}

export interface ResumenPedidos {
  total: number;
  por_estado: PedidosPorEstado[];
}

export interface Dashboard {
  productos: ResumenProductos;
  pedidos: ResumenPedidos;
}

export interface ProductoAdmin {
  id_producto: number;
  identificador: string;
  nombre: string;
  precio: string | number;
  existencia: number;
  moneda: string;
  estado: string;
  fecha_registro: string;
  categoria: string;
  imagen: string | null;
}

export interface DetalleProductoAdmin {
  id_producto: number;
  identificador: string;
  nombre: string;
  descripcion: string;
  precio: string | number;
  existencia: number;
  moneda: string;
  estado: string;
  fecha_registro: string;
  fecha_actualizacion: string | null;
  categoria: string;
  imagenes: { url_publica: string }[];
}

export interface Categoria {
  id_categoria: number;
  nombre: string;
  descripcion: string;
  activo: boolean;
}

export interface DatosNuevoProducto {
  identificador: string;
  id_categoria: number;
  nombre: string;
  descripcion: string;
  precio: number;
  existencia: number;
}

export interface DatosEdicionProducto {
  id_categoria?: number;
  nombre?: string;
  descripcion?: string;
  precio?: number;
  existencia?: number;
}

export interface DatosCategoria {
  nombre: string;
  descripcion?: string;
}

export interface UsuarioAdmin {
  id_usuario: number;
  nombre_usuario: string;
  correo: string;
  activo: boolean | string;
  rol: string;
  nombre: string | null;
  apellido_paterno: string | null;
  apellido_materno: string | null;
  telefono: string | null;
  fecha_registro: string;
}

export interface PedidoAdmin {
  id_pedido: number;
  numero_pedido: string;
  fecha_pedido: string;
  total: string | number;
  moneda: string;
  estado: string;
  nombre_usuario: string;
  comprador: string;
  direccion: string;
}

export interface ItemPedidoAdmin {
  id_producto: number;
  identificador: string;
  nombre: string;
  cantidad: number;
  precio_unitario: string | number;
  subtotal: string | number;
  vendedor: string;
}

export interface PedidoDetalleAdmin {
  id_pedido: number;
  numero_pedido: string;
  fecha_pedido: string;
  total: string | number;
  moneda: string;
  estado: string;
  nombre_usuario: string;
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
  detalle: ItemPedidoAdmin[];
}

export interface FiltrosPedidos {
  estado?: string;
  usuario?: string;
  folio?: string;
}

@Injectable({ providedIn: 'root' })
export class AdminService {
  private readonly http = inject(HttpClient);

  dashboard(token: string): Observable<Dashboard> {
    return this.http.get<Dashboard>(`${API_URL}/admin/dashboard`, {
      headers: this.headers(token),
    });
  }

  misProductos(token: string): Observable<ProductoAdmin[]> {
    return this.http.get<ProductoAdmin[]>(`${API_URL}/admin/productos/mios`, {
      headers: this.headers(token),
    });
  }

  detalleProducto(token: string, idProducto: number): Observable<DetalleProductoAdmin> {
    return this.http.get<DetalleProductoAdmin>(
      `${API_URL}/admin/productos/${idProducto}`,
      { headers: this.headers(token) },
    );
  }

  actualizarProducto(
    token: string,
    idProducto: number,
    datos: DatosEdicionProducto,
  ): Observable<unknown> {
    return this.http.put(`${API_URL}/admin/productos/${idProducto}`, datos, {
      headers: this.headers(token),
    });
  }

  cambiarEstadoProducto(
    token: string,
    idProducto: number,
    estado: 'activo' | 'inactivo',
  ): Observable<unknown> {
    return this.http.patch(
      `${API_URL}/admin/productos/${idProducto}/estado`,
      { estado },
      { headers: this.headers(token) },
    );
  }

  crearProducto(
    token: string,
    datos: DatosNuevoProducto,
    imagenes: File[],
  ): Observable<unknown> {
    const formData = new FormData();

    formData.append('identificador', datos.identificador);
    formData.append('id_categoria', String(datos.id_categoria));
    formData.append('nombre', datos.nombre);
    formData.append('descripcion', datos.descripcion);
    formData.append('precio', String(datos.precio));
    formData.append('existencia', String(datos.existencia));

    for (const imagen of imagenes) {
      formData.append('imagenes', imagen, imagen.name);
    }

    return this.http.post(`${API_URL}/admin/productos`, formData, {
      headers: this.headers(token),
    });
  }

  categorias(): Observable<Categoria[]> {
    return this.http.get<Categoria[]>(`${API_URL}/categorias`);
  }

  categoriasAdmin(token: string): Observable<Categoria[]> {
    return this.http.get<Categoria[]>(`${API_URL}/admin/categorias`, {
      headers: this.headers(token),
    });
  }

  crearCategoria(token: string, datos: DatosCategoria): Observable<unknown> {
    return this.http.post(`${API_URL}/admin/categorias`, datos, {
      headers: this.headers(token),
    });
  }

  cambiarEstadoCategoria(
    token: string,
    idCategoria: number,
    activo: boolean,
  ): Observable<unknown> {
    return this.http.patch(
      `${API_URL}/admin/categorias/${idCategoria}/estado`,
      { activo },
      { headers: this.headers(token) },
    );
  }

  usuarios(token: string): Observable<UsuarioAdmin[]> {
    return this.http.get<UsuarioAdmin[]>(`${API_URL}/admin/usuarios`, {
      headers: this.headers(token),
    });
  }

  pedidos(token: string, filtros: FiltrosPedidos = {}): Observable<PedidoAdmin[]> {
    const params: Record<string, string> = {};
    for (const [clave, valor] of Object.entries(filtros)) {
      if (valor !== undefined && valor !== null && valor !== '') {
        params[clave] = valor;
      }
    }
    return this.http.get<PedidoAdmin[]>(`${API_URL}/admin/pedidos`, {
      params,
      headers: this.headers(token),
    });
  }

  pedidoDetalle(token: string, idPedido: number): Observable<PedidoDetalleAdmin> {
    return this.http.get<PedidoDetalleAdmin>(`${API_URL}/admin/pedidos/${idPedido}`, {
      headers: this.headers(token),
    });
  }

  cambiarEstadoPedido(
    token: string,
    idPedido: number,
    estado: string,
  ): Observable<unknown> {
    return this.http.patch(
      `${API_URL}/admin/pedidos/${idPedido}/estado`,
      { estado },
      { headers: this.headers(token) },
    );
  }

  private headers(token: string): HttpHeaders {
    return new HttpHeaders({ Authorization: `Bearer ${token}` });
  }
}
