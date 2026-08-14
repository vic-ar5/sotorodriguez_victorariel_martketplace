import { Component, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../auth.service';
import { Logotipo } from '../logotipo';

const SIN_ESPACIOS = /^\S+$/;
const CARACTERES_CORREO = /^[a-zA-Z0-9.!#$%&'*\/=?^_`{|}~@-]+$/;

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule, RouterLink, Logotipo],
  templateUrl: './login.html',
  styleUrl: './login.css',
})
export class Login {
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);
  private readonly auth = inject(AuthService);

  protected readonly formulario = this.fb.nonNullable.group({
    correo: [
      '',
      [
        Validators.required,
        Validators.email,
        Validators.maxLength(150),
        Validators.pattern(CARACTERES_CORREO),
      ],
    ],
    contrasena: ['', [Validators.required, Validators.pattern(SIN_ESPACIOS)]],
  });

  protected cargando = false;
  protected error = '';
  protected cuentaCreada = false;

  protected readonly permitidosPassword = SIN_ESPACIOS;
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

  constructor() {
    this.cuentaCreada =
      new URLSearchParams(window.location.search).get('registrado') === '1';
  }

  protected enviar(): void {
    this.formulario.markAllAsTouched();
    if (this.formulario.invalid) {
      return;
    }

    const { correo, contrasena } = this.formulario.getRawValue();

    this.cargando = true;
    this.error = '';

    this.auth.login(correo, contrasena).subscribe({
      next: ({ token }) => {
        this.auth.guardarToken(token);
        this.router.navigate([this.auth.rutaSegunRol(token)]);
      },
      error: () => {
        this.cargando = false;
        this.error = 'Correo o contraseña incorrectos.';
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
    if (control.hasError('maxlength')) {
      const error = control.getError('maxlength') as {
        requiredLength: number;
      };
      return `Máximo ${error.requiredLength} caracteres.`;
    }
    if (control.hasError('pattern')) {
      return campo === 'correo'
        ? 'El correo contiene caracteres no válidos.'
        : 'La contraseña no puede contener espacios.';
    }
    return '';
  }
}
