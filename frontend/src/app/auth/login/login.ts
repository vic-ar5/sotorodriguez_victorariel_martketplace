import { Component, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../auth.service';
import { Logotipo } from '../logotipo';

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
      [Validators.required, Validators.email, Validators.maxLength(150)],
    ],
    contrasena: ['', [Validators.required]],
  });

  protected cargando = false;
  protected error = '';
  protected cuentaCreada = false;

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
    return '';
  }
}
