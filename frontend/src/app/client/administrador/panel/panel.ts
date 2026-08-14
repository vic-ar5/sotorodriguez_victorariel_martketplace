import { Component, ElementRef, inject, OnInit, ViewChild } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Logotipo } from '../../../auth/logotipo';
import { AuthService } from '../../../auth/auth.service';
import {
  AdminService,
  Categoria,
  Dashboard,
  DatosCategoria,
  DatosEdicionProducto,
  DatosNuevoProducto,
  DetalleProductoAdmin,
  PedidoAdmin,
  PedidoDetalleAdmin,
  ProductoAdmin,
  UsuarioAdmin,
} from './administrador.service';

const IDENTIFICADOR = /^[a-zA-Z0-9_-]+$/;
const NOMBRE_PRODUCTO = /^[a-zA-Z0-9áéíóúüñÁÉÍÓÚÜÑ,.'"()&°%#+\- ]+$/;
const DIGITO = /^[0-9]$/;
const PRECIO_DIGITO = /^[0-9.]$/;
const PRECIO_VALIDO = /^\d+(\.\d+)?$/;
const ENTERO_VALIDO = /^\d+$/;

const ESTADOS_PEDIDO = [
  'Pendiente',
  'Confirmado',
  'Preparando',
  'Enviado',
  'Entregado',
  'Cancelado',
];

type Pestana = 'inicio' | 'registrar' | 'productos' | 'categorias' | 'usuarios' | 'pedidos';

@Component({
  selector: 'app-administrador-panel',
  imports: [FormsModule, ReactiveFormsModule, RouterLink, Logotipo],
  templateUrl: './panel.html',
  styleUrl: './panel.css',
})
export class AdministradorPanel implements OnInit {
  private readonly router = inject(Router);
  private readonly auth = inject(AuthService);
  private readonly servicio = inject(AdminService);
  private readonly fb = inject(FormBuilder);
  private readonly token = this.auth.obtenerToken();

  protected pestanaActiva: Pestana = 'inicio';

  // ---------------------- Inicio (gráficas) ----------------------
  protected dashboard: Dashboard | null = null;
  protected cargandoDashboard = false;
  protected errorDashboard = '';

  // ---------------------- Registrar producto ----------------------
  protected readonly formulario = this.fb.nonNullable.group({
    identificador: [
      '',
      [
        Validators.required,
        Validators.maxLength(20),
        Validators.pattern(IDENTIFICADOR),
      ],
    ],
    nombre: [
      '',
      [
        Validators.required,
        Validators.maxLength(150),
        Validators.pattern(NOMBRE_PRODUCTO),
      ],
    ],
    descripcion: ['', [Validators.maxLength(500)]],
    precio: [
      '',
      [Validators.required, Validators.min(0), Validators.pattern(PRECIO_VALIDO)],
    ],
    existencia: ['', [Validators.min(0), Validators.pattern(ENTERO_VALIDO)]],
    id_categoria: [null as number | null, [Validators.required]],
  });

  protected registrando = false;
  protected mensajeRegistro = '';
  protected errorRegistro = '';
  protected imagenesSeleccionadas: { archivo: File; url: string }[] = [];

  @ViewChild('selectorImagenes')
  private selectorImagenes?: ElementRef<HTMLInputElement>;

  protected readonly permitidosIdentificador = IDENTIFICADOR;
  protected readonly permitidosNombre = NOMBRE_PRODUCTO;
  protected readonly permitidosDigito = DIGITO;
  protected readonly permitidosPrecio = PRECIO_DIGITO;

  // ---------------------- Productos ----------------------
  protected productos: ProductoAdmin[] = [];
  protected cargandoProductos = false;
  protected errorProductos = '';
  protected filtroDisponibilidad: 'todos' | 'disponibles' | 'no_disponibles' =
    'todos';

  protected productoSeleccionado: DetalleProductoAdmin | null = null;
  protected cargandoDetalle = false;
  protected mensajeDetalle = '';
  protected errorDetalle = '';

  protected editandoProducto = false;
  protected guardandoEdicion = false;
  protected cambiandoEstado = false;
  protected productoEditado: {
    nombre: string;
    descripcion: string;
    precio: number;
    existencia: number;
    id_categoria: number | null;
  } | null = null;
  protected categorias: Categoria[] = [];

  // ---------------------- Categorías ----------------------
  protected readonly formularioCategoria = this.fb.nonNullable.group({
    nombre: [
      '',
      [Validators.required, Validators.maxLength(60), Validators.pattern(NOMBRE_PRODUCTO)],
    ],
    descripcion: ['', [Validators.maxLength(200)]],
  });

  protected categoriasAdmin: Categoria[] = [];
  protected cargandoCategorias = false;
  protected errorCategorias = '';
  protected creandoCategoria = false;
  protected mensajeCategoria = '';
  protected errorCategoria = '';
  protected filtroCategorias: 'todas' | 'activas' | 'inactivas' = 'todas';
  protected cambiandoIdCategoria: number | null = null;

  // ---------------------- Usuarios ----------------------
  protected usuarios: UsuarioAdmin[] = [];
  protected cargandoUsuarios = false;
  protected errorUsuarios = '';

  // ---------------------- Pedidos ----------------------
  protected readonly estadosPedido = ESTADOS_PEDIDO;
  protected pedidos: PedidoAdmin[] = [];
  protected cargandoPedidos = false;
  protected errorPedidos = '';
  protected filtroEstadoPedido = '';
  protected filtroUsuarioPedido = '';
  protected filtroFolioPedido = '';

  protected pedidoSeleccionado: PedidoDetalleAdmin | null = null;
  protected cargandoDetallePedido = false;
  protected mensajePedido = '';
  protected errorPedido = '';
  protected cambiandoEstadoPedido = false;
  private pedidoAbiertoId: number | null = null;

  // ---------------------- Cuenta ----------------------
  protected menuAbierto = false;

  protected notificacion = '';
  private temporizadorNotificacion?: ReturnType<typeof setTimeout>;

  ngOnInit(): void {
    if (!this.token) {
      this.router.navigate(['/login']);
      return;
    }
    this.cargarDashboard();
    this.cargarCategorias();
  }

  // ---------------------- Pestañas ----------------------
  protected irA(pestana: Pestana): void {
    this.pestanaActiva = pestana;
    this.menuAbierto = false;
    this.errorRegistro = '';
    this.errorProductos = '';
    if (pestana === 'inicio') {
      this.cargarDashboard();
    }
    if (pestana === 'productos') {
      this.cargarProductos();
    }
    if (pestana === 'categorias') {
      this.cargarCategoriasAdmin();
    }
    if (pestana === 'usuarios') {
      this.cargarUsuarios();
    }
    if (pestana === 'pedidos') {
      this.cargarPedidos();
    }
  }

  // ---------------------- Inicio ----------------------
  protected cargarDashboard(): void {
    if (!this.token) {
      return;
    }

    const token = this.token;
    this.cargandoDashboard = true;
    this.errorDashboard = '';

    this.servicio.dashboard(token).subscribe({
      next: (datos) => {
        this.cargandoDashboard = false;
        this.dashboard = datos;
      },
      error: () => {
        this.cargandoDashboard = false;
        this.errorDashboard = 'No se pudo cargar el resumen.';
      },
    });
  }

  protected donutFondo(): string {
    const productos = this.dashboard?.productos;
    if (!productos || productos.total === 0) {
      return 'conic-gradient(var(--gris-fondo) 0deg 360deg)';
    }
    const porcentaje = (productos.disponibles / productos.total) * 100;
    return `conic-gradient(var(--azul-principal) 0deg ${porcentaje}%, var(--gris-fondo) ${porcentaje}deg 360deg)`;
  }

  protected maxPedidos(): number {
    const estados = this.dashboard?.pedidos.por_estado ?? [];
    return Math.max(1, ...estados.map((e) => Number(e.cantidad)));
  }

  protected alturaBarra(cantidad: number | string): string {
    const valor = Number(cantidad);
    return `${Math.max(4, (valor / this.maxPedidos()) * 100)}%`;
  }

  protected maxCategoria(): number {
    const categorias = this.dashboard?.productos.por_categoria ?? [];
    return Math.max(1, ...categorias.map((c) => Number(c.total)));
  }

  protected anchoCategoria(total: number | string): string {
    const valor = Number(total);
    return `${Math.max(4, (valor / this.maxCategoria()) * 100)}%`;
  }

  // ---------------------- Registrar producto ----------------------
  protected bloquearCaracteres(
    campo: string,
    permitidos: RegExp,
    event: Event,
  ): void {
    const antes = event as InputEvent;
    const texto = antes.data ?? antes.dataTransfer?.getData('text') ?? '';
    if (!texto) {
      return;
    }

    const filtrado = [...texto].filter((c) => permitidos.test(c)).join('');
    if (filtrado === texto) {
      return;
    }

    antes.preventDefault();

    if (!filtrado) {
      return;
    }

    const input = event.target as HTMLInputElement;
    const inicio = input.selectionStart ?? input.value.length;
    const fin = input.selectionEnd ?? inicio;
    const valor =
      input.value.slice(0, inicio) + filtrado + input.value.slice(fin);

    this.formulario.get(campo)?.setValue(valor);
    input.setSelectionRange(inicio + filtrado.length, inicio + filtrado.length);
  }

  protected abrirSelectorImagenes(): void {
    this.selectorImagenes?.nativeElement.click();
  }

  protected onImagenesSeleccionadas(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivos = Array.from(input.files ?? []);

    this.errorRegistro = '';

    for (const archivo of archivos) {
      const extension = (archivo.name.split('.').pop() ?? '').toLowerCase();
      const permitido = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'bmp',
        'svg',
        'avif',
        'tiff',
        'tif',
        'ico',
      ].includes(extension);

      if (!permitido) {
        this.errorRegistro =
          'Solo se permiten imágenes (JPG, PNG, GIF, WEBP, BMP, SVG, AVIF, TIFF, ICO).';
        continue;
      }
      if (archivo.size > 5 * 1024 * 1024) {
        this.errorRegistro = 'Cada imagen debe pesar menos de 5 MB.';
        continue;
      }
      if (this.imagenesSeleccionadas.length >= 5) {
        this.errorRegistro = 'Máximo 5 imágenes por producto.';
        break;
      }

      this.imagenesSeleccionadas.push({
        archivo,
        url: URL.createObjectURL(archivo),
      });
    }

    input.value = '';
  }

  protected quitarImagen(indice: number): void {
    const eliminada = this.imagenesSeleccionadas.splice(indice, 1)[0];
    if (eliminada) {
      URL.revokeObjectURL(eliminada.url);
    }
  }

  protected registrarProducto(): void {
    this.formulario.markAllAsTouched();
    if (this.formulario.invalid || !this.token) {
      return;
    }

    const token = this.token;
    const valores = this.formulario.getRawValue();
    const datos: DatosNuevoProducto = {
      identificador: valores.identificador.trim(),
      id_categoria: Number(valores.id_categoria),
      nombre: valores.nombre.trim(),
      descripcion: valores.descripcion.trim(),
      precio: Number(valores.precio),
      existencia: Number(valores.existencia || 0),
    };

    this.registrando = true;
    this.mensajeRegistro = '';
    this.errorRegistro = '';

    this.servicio.crearProducto(token, datos, this.imagenesSeleccionadas.map((i) => i.archivo)).subscribe({
      next: () => {
        this.registrando = false;
        this.mensajeRegistro = 'Producto registrado correctamente.';
        this.formulario.reset();
        this.imagenesSeleccionadas.forEach((i) => URL.revokeObjectURL(i.url));
        this.imagenesSeleccionadas = [];
        this.mostrarNotificacion('Producto registrado.');
      },
      error: (err) => {
        this.registrando = false;
        const detalle = String(err?.error?.error ?? '');
        this.errorRegistro = detalle.toLowerCase().includes('identificador')
          ? 'El identificador del producto ya está registrado.'
          : 'No se pudo registrar el producto. Verifica los datos o la configuración de Google Drive.';
      },
    });
  }

  protected mensajeError(campo: string): string {
    const control = this.formulario.get(campo);
    if (!control || !control.touched || !control.errors) {
      return '';
    }

    if (control.hasError('required')) {
      return 'Este campo es obligatorio.';
    }
    if (control.hasError('maxlength')) {
      const error = control.getError('maxlength') as {
        requiredLength: number;
      };
      return `Máximo ${error.requiredLength} caracteres.`;
    }
    if (control.hasError('min')) {
      return 'El valor no puede ser negativo.';
    }
    if (control.hasError('pattern')) {
      switch (campo) {
        case 'identificador':
          return 'Solo letras, números, guiones y guion bajo.';
        case 'precio':
          return 'Ingresa solo números y punto decimal.';
        case 'existencia':
          return 'Ingresa solo números enteros.';
        default:
          return 'El campo contiene caracteres no válidos.';
      }
    }
    return '';
  }

  // ---------------------- Productos ----------------------
  protected cargarProductos(): void {
    if (!this.token) {
      return;
    }

    const token = this.token;
    this.cargandoProductos = true;
    this.errorProductos = '';

    this.servicio.misProductos(token).subscribe({
      next: (productos) => {
        this.cargandoProductos = false;
        this.productos = productos;
      },
      error: () => {
        this.cargandoProductos = false;
        this.errorProductos = 'No se pudieron cargar tus productos.';
      },
    });
  }

  protected esDisponible(p: { estado: string; existencia: number }): boolean {
    return p.estado === 'activo' && p.existencia > 0;
  }

  protected productosFiltrados(): ProductoAdmin[] {
    if (this.filtroDisponibilidad === 'todos') {
      return this.productos;
    }

    return this.productos.filter((producto) =>
      this.filtroDisponibilidad === 'disponibles'
        ? this.esDisponible(producto)
        : !this.esDisponible(producto),
    );
  }

  protected abrirDetalle(producto: ProductoAdmin): void {
    if (!this.token) {
      return;
    }
    this.productoSeleccionado = null;
    this.mensajeDetalle = '';
    this.errorDetalle = '';
    this.editandoProducto = false;
    this.cargarDetalle(producto.id_producto);
  }

  private cargarDetalle(idProducto: number): void {
    if (!this.token) {
      return;
    }

    const token = this.token;
    this.cargandoDetalle = true;

    this.servicio.detalleProducto(token, idProducto).subscribe({
      next: (detalle) => {
        this.cargandoDetalle = false;
        this.productoSeleccionado = detalle;
      },
      error: () => {
        this.cargandoDetalle = false;
        this.errorDetalle = 'No se pudo cargar el detalle del producto.';
      },
    });
  }

  protected cerrarDetalle(): void {
    this.productoSeleccionado = null;
    this.editandoProducto = false;
    this.mensajeDetalle = '';
    this.errorDetalle = '';
  }

  protected imagenDetalle(): string {
    return (
      this.productoSeleccionado?.imagenes?.find((i) => i.url_publica)
        ?.url_publica ?? ''
    );
  }

  protected iniciarEdicion(): void {
    const producto = this.productoSeleccionado;
    if (!producto) {
      return;
    }
    this.productoEditado = {
      nombre: producto.nombre,
      descripcion: producto.descripcion,
      precio: Number(producto.precio),
      existencia: producto.existencia,
      id_categoria: this.idCategoriaDe(producto.categoria),
    };
    this.editandoProducto = true;
    this.mensajeDetalle = '';
    this.errorDetalle = '';
  }

  private idCategoriaDe(nombre: string): number | null {
    return (
      this.categorias.find((c) => c.nombre === nombre)?.id_categoria ?? null
    );
  }

  protected cancelarEdicion(): void {
    this.editandoProducto = false;
    this.productoEditado = null;
    this.errorDetalle = '';
  }

  protected guardarEdicion(): void {
    if (!this.token || !this.productoSeleccionado || !this.productoEditado) {
      return;
    }

    const editado = this.productoEditado;

    if (!editado.nombre.trim()) {
      this.errorDetalle = 'El nombre es obligatorio.';
      return;
    }
    if (
      editado.precio === null ||
      editado.precio === undefined ||
      !Number.isFinite(Number(editado.precio)) ||
      Number(editado.precio) < 0
    ) {
      this.errorDetalle = 'Ingresa un precio válido.';
      return;
    }
    if (
      editado.existencia === null ||
      editado.existencia === undefined ||
      !Number.isFinite(Number(editado.existencia)) ||
      Number(editado.existencia) < 0
    ) {
      this.errorDetalle = 'Ingresa una existencia válida.';
      return;
    }

    const token = this.token;
    const idProducto = this.productoSeleccionado.id_producto;
    const datos: DatosEdicionProducto = {
      nombre: editado.nombre.trim(),
      descripcion: editado.descripcion.trim(),
      precio: Number(editado.precio),
      existencia: Number(editado.existencia),
    };
    if (editado.id_categoria !== null && editado.id_categoria !== undefined) {
      datos.id_categoria = editado.id_categoria;
    }

    this.guardandoEdicion = true;
    this.mensajeDetalle = '';
    this.errorDetalle = '';

    this.servicio.actualizarProducto(token, idProducto, datos).subscribe({
      next: () => {
        this.guardandoEdicion = false;
        this.editandoProducto = false;
        this.productoEditado = null;
        this.cargarDetalle(idProducto);
        this.cargarProductos();
        this.cargarDashboard();
        this.mostrarNotificacion('Producto actualizado.');
      },
      error: (err) => {
        this.guardandoEdicion = false;
        this.errorDetalle =
          err?.error?.error ?? 'No se pudo actualizar el producto.';
      },
    });
  }

  protected cambiarDisponibilidad(estado: 'activo' | 'inactivo'): void {
    if (!this.token || !this.productoSeleccionado) {
      return;
    }

    const token = this.token;
    const idProducto = this.productoSeleccionado.id_producto;

    this.cambiandoEstado = true;
    this.mensajeDetalle = '';
    this.errorDetalle = '';

    this.servicio
      .cambiarEstadoProducto(token, idProducto, estado)
      .subscribe({
        next: () => {
          this.cambiandoEstado = false;
          this.cargarDetalle(idProducto);
          this.cargarProductos();
          this.cargarDashboard();
          this.mostrarNotificacion(
            estado === 'activo'
              ? 'Producto habilitado.'
              : 'Producto deshabilitado.',
          );
        },
        error: (err) => {
          this.cambiandoEstado = false;
          this.errorDetalle =
            err?.error?.error ?? 'No se pudo cambiar el estado del producto.';
        },
      });
  }

  protected cargarCategorias(): void {
    this.servicio.categorias().subscribe({
      next: (categorias) => {
        this.categorias = categorias;
      },
      error: () => {
        // Si falla, el selector de categoría queda vacío.
      },
    });
  }

  // ---------------------- Categorías ----------------------
  protected cargarCategoriasAdmin(): void {
    if (!this.token) {
      return;
    }

    const token = this.token;
    this.cargandoCategorias = true;
    this.errorCategorias = '';

    this.servicio.categoriasAdmin(token).subscribe({
      next: (categorias) => {
        this.cargandoCategorias = false;
        this.categoriasAdmin = categorias.map((categoria) => ({
          ...categoria,
          activo: this.esActiva(categoria.activo),
        }));
      },
      error: () => {
        this.cargandoCategorias = false;
        this.errorCategorias = 'No se pudieron cargar las categorías.';
      },
    });
  }

  protected esActiva(activo: unknown): boolean {
    return activo === true || activo === 1 || activo === '1' || activo === 't' || activo === 'true';
  }

  protected categoriasFiltradas(): Categoria[] {
    if (this.filtroCategorias === 'todas') {
      return this.categoriasAdmin;
    }

    return this.categoriasAdmin.filter((categoria) =>
      this.filtroCategorias === 'activas'
        ? categoria.activo === true
        : categoria.activo === false,
    );
  }

  protected mensajeErrorCategoria(campo: string): string {
    const control = this.formularioCategoria.get(campo);
    if (!control || !control.touched || !control.errors) {
      return '';
    }

    if (control.hasError('required')) {
      return 'Este campo es obligatorio.';
    }
    if (control.hasError('maxlength')) {
      const error = control.getError('maxlength') as {
        requiredLength: number;
      };
      return `Máximo ${error.requiredLength} caracteres.`;
    }
    if (control.hasError('pattern')) {
      return 'El campo contiene caracteres no válidos.';
    }
    return '';
  }

  protected registrarCategoria(): void {
    this.formularioCategoria.markAllAsTouched();
    if (this.formularioCategoria.invalid || !this.token) {
      return;
    }

    const token = this.token;
    const valores = this.formularioCategoria.getRawValue();
    const datos: DatosCategoria = {
      nombre: valores.nombre.trim(),
      descripcion: valores.descripcion.trim(),
    };

    this.creandoCategoria = true;
    this.mensajeCategoria = '';
    this.errorCategoria = '';

    this.servicio.crearCategoria(token, datos).subscribe({
      next: () => {
        this.creandoCategoria = false;
        this.mensajeCategoria = 'Categoría creada correctamente.';
        this.formularioCategoria.reset();
        this.cargarCategoriasAdmin();
        this.cargarCategorias();
        this.mostrarNotificacion('Categoría agregada.');
      },
      error: (err) => {
        this.creandoCategoria = false;
        this.errorCategoria =
          err?.error?.error ?? 'No se pudo crear la categoría.';
      },
    });
  }

  protected cambiarEstadoCategoria(categoria: Categoria): void {
    if (!this.token || this.cambiandoIdCategoria !== null) {
      return;
    }

    const token = this.token;
    const idCategoria = categoria.id_categoria;
    const activo = !this.esActiva(categoria.activo);

    this.cambiandoIdCategoria = idCategoria;
    this.errorCategoria = '';
    this.mensajeCategoria = '';

    this.servicio.cambiarEstadoCategoria(token, idCategoria, activo).subscribe({
      next: () => {
        this.cambiandoIdCategoria = null;
        this.cargarCategoriasAdmin();
        this.cargarCategorias();
        this.mostrarNotificacion(
          activo ? 'Categoría activada.' : 'Categoría desactivada.',
        );
      },
      error: (err) => {
        this.cambiandoIdCategoria = null;
        this.errorCategorias =
          err?.error?.error ?? 'No se pudo cambiar el estado de la categoría.';
      },
    });
  }

  // ---------------------- Usuarios ----------------------
  protected cargarUsuarios(): void {
    if (!this.token) {
      return;
    }

    const token = this.token;
    this.cargandoUsuarios = true;
    this.errorUsuarios = '';

    this.servicio.usuarios(token).subscribe({
      next: (usuarios) => {
        this.cargandoUsuarios = false;
        this.usuarios = usuarios.map((usuario) => ({
          ...usuario,
          activo: this.esActiva(usuario.activo),
        }));
      },
      error: () => {
        this.cargandoUsuarios = false;
        this.errorUsuarios = 'No se pudieron cargar los usuarios.';
      },
    });
  }

  protected nombreCompleto(usuario: UsuarioAdmin): string {
    return [usuario.nombre, usuario.apellido_paterno, usuario.apellido_materno]
      .filter(Boolean)
      .join(' ')
      .trim();
  }

  // ---------------------- Pedidos ----------------------
  protected cargarPedidos(): void {
    if (!this.token) {
      return;
    }

    const token = this.token;
    this.cargandoPedidos = true;
    this.errorPedidos = '';

    this.servicio
      .pedidos(token, {
        estado: this.filtroEstadoPedido,
        usuario: this.filtroUsuarioPedido.trim(),
        folio: this.filtroFolioPedido.trim(),
      })
      .subscribe({
        next: (pedidos) => {
          this.cargandoPedidos = false;
          this.pedidos = pedidos;
        },
        error: () => {
          this.cargandoPedidos = false;
          this.errorPedidos = 'No se pudieron cargar los pedidos.';
        },
      });
  }

  protected limpiarFiltrosPedidos(): void {
    this.filtroEstadoPedido = '';
    this.filtroUsuarioPedido = '';
    this.filtroFolioPedido = '';
    this.cargarPedidos();
  }

  protected compradorPedido(pedido: PedidoAdmin): string {
    return pedido.comprador?.trim() || pedido.nombre_usuario || '—';
  }

  protected abrirPedido(pedido: PedidoAdmin): void {
    if (!this.token) {
      return;
    }

    this.pedidoSeleccionado = null;
    this.mensajePedido = '';
    this.errorPedido = '';
    this.pedidoAbiertoId = pedido.id_pedido;
    this.cargarDetallePedido(pedido.id_pedido);
  }

  private cargarDetallePedido(idPedido: number): void {
    if (!this.token) {
      return;
    }

    const token = this.token;
    this.cargandoDetallePedido = true;

    this.servicio.pedidoDetalle(token, idPedido).subscribe({
      next: (detalle) => {
        this.cargandoDetallePedido = false;
        if (this.pedidoAbiertoId === idPedido) {
          this.pedidoSeleccionado = detalle;
        }
      },
      error: () => {
        this.cargandoDetallePedido = false;
        if (this.pedidoAbiertoId === idPedido) {
          this.errorPedido = 'No se pudo cargar el detalle del pedido.';
        }
      },
    });
  }

  protected cerrarPedido(): void {
    this.pedidoAbiertoId = null;
    this.pedidoSeleccionado = null;
    this.cargandoDetallePedido = false;
    this.mensajePedido = '';
    this.errorPedido = '';
    this.cambiandoEstadoPedido = false;
  }

  protected accionesPedido(estado: string): { estado: string; etiqueta: string }[] {
    switch (estado) {
      case 'Confirmado':
        return [
          { estado: 'Preparando', etiqueta: 'Preparando' },
          { estado: 'Cancelado', etiqueta: 'Cancelar' },
        ];
      case 'Preparando':
        return [
          { estado: 'Enviado', etiqueta: 'Enviado' },
          { estado: 'Cancelado', etiqueta: 'Cancelar' },
        ];
      case 'Enviado':
        return [
          { estado: 'Entregado', etiqueta: 'Entregado' },
          { estado: 'Cancelado', etiqueta: 'Cancelar' },
        ];
      case 'Pendiente':
        return [{ estado: 'Cancelado', etiqueta: 'Cancelar' }];
      default:
        return [];
    }
  }

  protected cambiarEstadoPedido(estado: string): void {
    if (!this.token || !this.pedidoSeleccionado || this.cambiandoEstadoPedido) {
      return;
    }

    const token = this.token;
    const pedido = this.pedidoSeleccionado;

    this.cambiandoEstadoPedido = true;
    this.mensajePedido = '';
    this.errorPedido = '';

    this.servicio.cambiarEstadoPedido(token, pedido.id_pedido, estado).subscribe({
      next: () => {
        this.cambiandoEstadoPedido = false;
        this.cargarDetallePedido(pedido.id_pedido);
        this.cargarPedidos();
        this.cargarDashboard();
        this.mostrarNotificacion(`Pedido ${pedido.numero_pedido} marcado como ${estado}.`);
      },
      error: (err) => {
        this.cambiandoEstadoPedido = false;
        this.errorPedido =
          err?.error?.error ?? 'No se pudo cambiar el estado del pedido.';
      },
    });
  }

  // ---------------------- Cuenta ----------------------
  protected alternarMenu(): void {
    this.menuAbierto = !this.menuAbierto;
  }

  protected cerrarSesion(): void {
    this.auth.eliminarToken();
    this.router.navigate(['/login']);
  }

  // ---------------------- Utilidades ----------------------
  protected formatearFecha(fecha: string): string {
    return new Date(fecha).toLocaleString('es-MX', {
      dateStyle: 'long',
      timeStyle: 'short',
    });
  }

  protected formatearPrecio(precio: string | number | undefined): string {
    const valor = Number(precio ?? 0);
    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: 'MXN',
    }).format(valor);
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
}
