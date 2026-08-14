import { Component, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../auth.service';
import { Logotipo } from '../logotipo';

const TELEFONO_MEXICO = /^[0-9]{10}$/;
const NOMBRE_LETRAS = /^[a-zA-ZáéíóúüñÁÉÍÓÚÜÑ ]+$/;
const USUARIO_ALFANUMERICO = /^[a-zA-Z0-9_]+$/;
const SIN_ESPACIOS = /^\S+$/;
const DIGITO = /^[0-9]$/;
const CARACTERES_CORREO = /^[a-zA-Z0-9.!#$%&'*\/=?^_`{|}~@-]+$/;

@Component({
  selector: 'app-registro',
  imports: [ReactiveFormsModule, RouterLink, Logotipo],
  templateUrl: './registro.html',
  styleUrl: './registro.css',
})
export class Registro {
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);
  private readonly auth = inject(AuthService);

  protected readonly formulario = this.fb.nonNullable.group({
    nombre_usuario: [
      '',
      [
        Validators.required,
        Validators.maxLength(50),
        Validators.pattern(USUARIO_ALFANUMERICO),
      ],
    ],
    correo: [
      '',
      [
        Validators.required,
        Validators.email,
        Validators.maxLength(150),
        Validators.pattern(CARACTERES_CORREO),
      ],
    ],
    contrasena: [
      '',
      [
        Validators.required,
        Validators.minLength(8),
        Validators.pattern(SIN_ESPACIOS),
      ],
    ],
    nombre: [
      '',
      [
        Validators.required,
        Validators.maxLength(80),
        Validators.pattern(NOMBRE_LETRAS),
      ],
    ],
    apellido_paterno: [
      '',
      [
        Validators.required,
        Validators.maxLength(80),
        Validators.pattern(NOMBRE_LETRAS),
      ],
    ],
    apellido_materno: [
      '',
      [Validators.maxLength(80), Validators.pattern(NOMBRE_LETRAS)],
    ],
    telefono: ['', [Validators.pattern(TELEFONO_MEXICO)]],
  });

  protected cargando = false;
  protected error = '';

  protected readonly permitidosNombre = NOMBRE_LETRAS;
  protected readonly permitidosUsuario = USUARIO_ALFANUMERICO;
  protected readonly permitidosPassword = SIN_ESPACIOS;
  protected readonly permitidosDigito = DIGITO;
  protected readonly permitidosCorreo = CARACTERES_CORREO;

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

  protected enviar(): void {
    this.formulario.markAllAsTouched();
    if (this.formulario.invalid) {
      return;
    }

    const valores = this.formulario.getRawValue();
    const datos = {
      ...valores,
      apellido_materno: valores.apellido_materno || undefined,
      telefono: valores.telefono || undefined,
    };

    this.cargando = true;
    this.error = '';

    this.auth.registrar(datos).subscribe({
      next: () => {
        this.router.navigate(['/login'], {
          queryParams: { registrado: '1' },
        });
      },
      error: () => {
        this.cargando = false;
        this.error =
          'No se pudo crear la cuenta. Verifica tus datos o intenta con otro correo.';
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
    if (control.hasError('email')) {
      return 'Ingresa un correo electrónico válido.';
    }
    if (control.hasError('minlength')) {
      const error = control.getError('minlength') as {
        requiredLength: number;
      };
      return `Mínimo ${error.requiredLength} caracteres.`;
    }
    if (control.hasError('maxlength')) {
      const error = control.getError('maxlength') as {
        requiredLength: number;
      };
      return `Máximo ${error.requiredLength} caracteres.`;
    }
    if (control.hasError('pattern')) {
      switch (campo) {
        case 'telefono':
          return 'Ingresa 10 dígitos numéricos.';
        case 'nombre_usuario':
          return 'Solo letras, números y guion bajo.';
        case 'contrasena':
          return 'La contraseña no puede contener espacios.';
        case 'correo':
          return 'El correo contiene caracteres no válidos.';
        default:
          return 'Solo se permiten letras.';
      }
    }
    return '';
  }
}
