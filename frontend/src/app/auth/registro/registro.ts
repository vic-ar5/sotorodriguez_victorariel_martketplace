import { Component, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../auth.service';
import { Logotipo } from '../logotipo';

const TELEFONO_MEXICO = /^[0-9]{10}$/;

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
    nombre_usuario: ['', [Validators.required, Validators.maxLength(50)]],
    correo: [
      '',
      [Validators.required, Validators.email, Validators.maxLength(150)],
    ],
    contrasena: ['', [Validators.required, Validators.minLength(8)]],
    nombre: ['', [Validators.required, Validators.maxLength(80)]],
    apellido_paterno: ['', [Validators.required, Validators.maxLength(80)]],
    apellido_materno: ['', [Validators.maxLength(80)]],
    telefono: ['', [Validators.pattern(TELEFONO_MEXICO)]],
  });

  protected cargando = false;
  protected error = '';

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
      return 'Ingresa 10 dígitos numéricos.';
    }
    return '';
  }
}
