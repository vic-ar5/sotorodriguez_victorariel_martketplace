import { Component, inject, OnInit } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { Logotipo } from '../../../auth/logotipo';
import { AuthService } from '../../../auth/auth.service';
import {
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

  protected menuAbierto = false;
  protected perfil: MiPerfil | null = null;
  protected editandoPerfil = false;
  protected perfilEditado: MiPerfil | null = null;
  protected mensajePerfil = '';
  protected errorPerfil = '';

  protected productoSeleccionado: DetalleProducto | null = null;
  protected agregandoCarrito = false;
  protected mensajeCarrito = '';

  ngOnInit(): void {
    if (!this.token) {
      this.router.navigate(['/login']);
      return;
    }
    this.cargarProductos();
    this.cargarPerfil();
  }

  protected cargarProductos(): void {
    this.cargando = true;
    this.errorCarga = '';
    this.servicio.productos().subscribe({
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

  protected buscar(): void {
    const termino = this.terminoBusqueda.trim();
    if (!termino) {
      this.cargarProductos();
      return;
    }
    this.cargando = true;
    this.errorCarga = '';
    this.servicio.buscarProductos(termino).subscribe({
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

  protected alternarMenu(): void {
    this.menuAbierto = !this.menuAbierto;
    if (!this.menuAbierto) {
      this.editandoPerfil = false;
    }
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
    this.agregandoCarrito = true;
    this.mensajeCarrito = '';
    this.servicio
      .agregarAlCarrito(this.token, this.productoSeleccionado.id_producto)
      .subscribe({
        next: () => {
          this.agregandoCarrito = false;
          this.mensajeCarrito = 'Producto agregado al carrito.';
        },
        error: () => {
          this.agregandoCarrito = false;
          this.mensajeCarrito =
            'No se pudo agregar el producto al carrito.';
        },
      });
  }

  protected formatearPrecio(precio: string | number | undefined): string {
    const valor = Number(precio ?? 0);
    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: 'MXN',
    }).format(valor);
  }
}
