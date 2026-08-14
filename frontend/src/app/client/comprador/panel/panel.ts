import { Component, inject, OnInit } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { Logotipo } from '../../../auth/logotipo';
import { AuthService } from '../../../auth/auth.service';
import {
  Carrito,
  CarritoItem,
  Categoria,
  CompradorService,
  DetalleProducto,
  MiPerfil,
  Producto,
} from './comprador.service';

@Component({
  selector: 'app-comprador-panel',
  imports: [FormsModule, RouterLink, Logotipo],
  templateUrl: './panel.html',
  styleUrl: './panel.css',
})
export class CompradorPanel implements OnInit {
  private readonly router = inject(Router);
  private readonly auth = inject(AuthService);
  private readonly servicio = inject(CompradorService);
  private readonly token = this.auth.obtenerToken();

  protected productos: Producto[] = [];
  protected cargando = true;
  protected errorCarga = '';
  protected terminoBusqueda = '';

  protected filtrosAbiertos = false;
  protected categorias: Categoria[] = [];
  protected filtro = {
    id_categoria: '',
    precio_min: '',
    precio_max: '',
    disponibilidad: '',
    orden: '',
  };

  protected menuAbierto = false;
  protected perfil: MiPerfil | null = null;
  protected editandoPerfil = false;
  protected perfilEditado: MiPerfil | null = null;
  protected mensajePerfil = '';
  protected errorPerfil = '';

  protected productoSeleccionado: DetalleProducto | null = null;
  protected agregandoCarrito = false;
  protected mensajeCarrito = '';
  protected cantidadAgregar = 1;

  protected carrito: Carrito = { id_carrito: null, items: [], total: 0 };
  protected carritoAbierto = false;
  protected carritoTotalProductos = 0;
  protected mensajePago = '';
  protected mensajeCart = '';

  protected notificacion = '';
  private temporizadorNotificacion?: ReturnType<typeof setTimeout>;

  ngOnInit(): void {
    if (!this.token) {
      this.router.navigate(['/login']);
      return;
    }
    this.cargarProductos();
    this.cargarPerfil();
    this.cargarCategorias();
    this.cargarCarrito();
  }

  protected cargarProductos(): void {
    this.cargando = true;
    this.errorCarga = '';
    this.servicio
      .filtrarProductos({
        id_categoria: this.filtro.id_categoria,
        precio_min: this.filtro.precio_min,
        precio_max: this.filtro.precio_max,
        disponibilidad: this.filtro.disponibilidad,
        nombre: this.terminoBusqueda.trim(),
        orden: this.filtro.orden,
      })
      .subscribe({
        next: (datos) => {
          this.productos = datos;
          this.cargando = false;
        },
        error: () => {
          this.cargando = false;
          this.errorCarga = 'No se pudieron cargar los productos.';
        },
      });
  }

  protected cargarCategorias(): void {
    this.servicio.categorias().subscribe({
      next: (categorias) => {
        this.categorias = categorias;
      },
      error: () => {
        // Si falla, el filtro de categoría simplemente queda vacío.
      },
    });
  }

  protected alternarFiltros(): void {
    this.filtrosAbiertos = !this.filtrosAbiertos;
    this.menuAbierto = false;
    this.editandoPerfil = false;
  }

  protected aplicarFiltros(): void {
    this.filtrosAbiertos = false;
    this.cargarProductos();
  }

  protected limpiarFiltros(): void {
    this.filtro = {
      id_categoria: '',
      precio_min: '',
      precio_max: '',
      disponibilidad: '',
      orden: '',
    };
    this.aplicarFiltros();
  }

  protected buscar(): void {
    this.cargarProductos();
  }

  protected alternarMenu(): void {
    this.menuAbierto = !this.menuAbierto;
    if (!this.menuAbierto) {
      this.editandoPerfil = false;
    }
    this.filtrosAbiertos = false;
  }

  protected cargarPerfil(): void {
    if (!this.token) {
      return;
    }
    this.servicio.miPerfil(this.token).subscribe({
      next: (perfil) => {
        this.perfil = perfil;
      },
      error: () => {
        this.auth.eliminarToken();
        this.router.navigate(['/login']);
      },
    });
  }

  protected inicialPerfil(): string {
    const nombre = this.perfil?.nombre?.trim();
    return nombre ? nombre.charAt(0).toUpperCase() : 'U';
  }

  protected iniciarEdicion(): void {
    if (!this.perfil) {
      return;
    }
    this.perfilEditado = { ...this.perfil };
    this.editandoPerfil = true;
    this.mensajePerfil = '';
    this.errorPerfil = '';
  }

  protected guardarPerfil(): void {
    if (!this.token || !this.perfilEditado) {
      return;
    }
    const { nombre, apellido_paterno, apellido_materno, telefono } =
      this.perfilEditado;
    this.servicio
      .actualizarMiPerfil(this.token, {
        nombre,
        apellido_paterno,
        apellido_materno,
        telefono,
      })
      .subscribe({
        next: () => {
          this.perfil = { ...this.perfilEditado! };
          this.editandoPerfil = false;
          this.mensajePerfil = 'Perfil actualizado.';
        },
        error: () => {
          this.errorPerfil = 'No se pudo actualizar el perfil.';
        },
      });
  }

  protected cancelarEdicion(): void {
    this.editandoPerfil = false;
    this.perfilEditado = null;
  }

  protected cerrarSesion(): void {
    this.auth.eliminarToken();
    this.router.navigate(['/login']);
  }

  protected abrirDetalle(producto: Producto): void {
    this.mensajeCarrito = '';
    this.cantidadAgregar = 1;
    this.servicio.detalleProducto(producto.id_producto).subscribe({
      next: (detalle) => {
        this.productoSeleccionado = detalle;
      },
      error: () => {
        this.errorCarga = 'No se pudo cargar el detalle del producto.';
      },
    });
  }

  protected cerrarDetalle(): void {
    this.productoSeleccionado = null;
    this.mensajeCarrito = '';
  }

  protected imagenDetalle(): string {
    return (
      this.productoSeleccionado?.imagenes?.find((i) => i.url_publica)
        ?.url_publica ?? ''
    );
  }

  protected agregarAlCarrito(): void {
    if (!this.token || !this.productoSeleccionado) {
      return;
    }

    const detalle = this.productoSeleccionado;
    const cantidad = Number(this.cantidadAgregar);
    const disponible = detalle.existencia;

    if (!cantidad || cantidad < 1) {
      this.mensajeCarrito = 'La cantidad debe ser al menos 1.';
      return;
    }

    if (cantidad > disponible) {
      this.mensajeCarrito = `Superaste el stock del producto (máximo ${disponible}).`;
      return;
    }

    this.agregandoCarrito = true;
    this.mensajeCarrito = '';
    this.servicio
      .agregarAlCarrito(this.token, detalle.id_producto, cantidad)
      .subscribe({
        next: () => {
          this.agregandoCarrito = false;
          this.mensajeCarrito =
            'Producto agregado al carrito.';
          this.mostrarNotificacion(
            `${detalle.nombre} agregado al carrito (×${cantidad}).`,
          );
          if (this.productoSeleccionado) {
            this.productoSeleccionado.existencia = Math.max(
              0,
              this.productoSeleccionado.existencia - cantidad,
            );
          }
          this.cargarCarrito();
          this.cargarProductos();
        },
        error: (err) => {
          this.agregandoCarrito = false;
          const mensaje = err?.error?.error ?? '';
          if (String(mensaje).toLowerCase().includes('stock')) {
            this.mensajeCarrito = 'Superaste el stock del producto.';
          } else {
            this.mensajeCarrito =
              'No se pudo agregar el producto al carrito.';
          }
        },
      });
  }

  protected cargarCarrito(): void {
    if (!this.token) {
      return;
    }
    this.servicio.carrito(this.token).subscribe({
      next: (carrito) => {
        this.carrito = carrito;
        this.carritoTotalProductos = carrito.items.reduce(
          (suma, item) => suma + item.cantidad,
          0,
        );
      },
      error: () => {
        // Si falla, el carrito se conserva tal como está.
      },
    });
  }

  protected alternarCarrito(): void {
    this.carritoAbierto = !this.carritoAbierto;
    this.mensajePago = '';
    this.mensajeCart = '';
    this.menuAbierto = false;
    this.filtrosAbiertos = false;
  }

  protected cambiarCantidad(item: CarritoItem, nueva: string | number): void {
    if (!this.token) {
      return;
    }

    const cantidad = Number(nueva);

    if (!cantidad || cantidad < 1) {
      return;
    }

    if (cantidad > item.existencia + item.cantidad) {
      this.mensajeCart = `No hay más stock de "${item.nombre}" (máximo ${item.existencia + item.cantidad}).`;
      return;
    }

    this.servicio
      .modificarCantidad(this.token, item.id_producto, cantidad)
      .subscribe({
        next: () => {
          this.mensajeCart = '';
          this.cargarCarrito();
          this.cargarProductos();
        },
        error: (err) => {
          const mensaje = err?.error?.error ?? '';
          if (String(mensaje).toLowerCase().includes('stock')) {
            this.mensajeCart = 'Superaste el stock del producto.';
          } else {
            this.mensajeCart = 'No se pudo actualizar la cantidad.';
          }
        },
      });
  }

  protected eliminarItem(item: CarritoItem): void {
    if (!this.token) {
      return;
    }
    this.servicio
      .eliminarItemDelCarrito(this.token, item.id_producto)
      .subscribe({
        next: () => {
          this.mensajeCart = '';
          this.cargarCarrito();
          this.cargarProductos();
        },
        error: () => {
          this.mensajeCart = 'No se pudo eliminar el producto del carrito.';
        },
      });
  }

  protected vaciarCarrito(): void {
    if (!this.token) {
      return;
    }
    this.servicio.vaciarCarrito(this.token).subscribe({
      next: () => {
        this.carrito = { id_carrito: null, items: [], total: 0 };
        this.carritoTotalProductos = 0;
        this.carritoAbierto = false;
        this.mensajePago = '';
        this.mensajeCart = '';
        this.cargarProductos();
      },
      error: () => {
        this.carritoAbierto = false;
      },
    });
  }

  protected pagarCarrito(): void {
    this.mensajePago = 'El pago estará disponible próximamente.';
  }

  protected mostrarNotificacion(mensaje: string): void {
    this.notificacion = mensaje;
    if (this.temporizadorNotificacion) {
      clearTimeout(this.temporizadorNotificacion);
    }
    this.temporizadorNotificacion = setTimeout(() => {
      this.notificacion = '';
    }, 10000);
  }

  protected cerrarNotificacion(): void {
    if (this.temporizadorNotificacion) {
      clearTimeout(this.temporizadorNotificacion);
    }
    this.notificacion = '';
  }

  protected formatearPrecio(precio: string | number | undefined): string {
    const valor = Number(precio ?? 0);
    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: 'MXN',
    }).format(valor);
  }
}
