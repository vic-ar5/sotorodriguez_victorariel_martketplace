import { Component } from '@angular/core';

@Component({
  selector: 'app-logotipo',
  template: `
    <svg
      viewBox="0 0 500 120"
      xmlns="http://www.w3.org/2000/svg"
      role="img"
      aria-label="Logotipo de Shoptify"
    >
      <path class="outline" d="M128 52c0-14 12-25 28-25s28 11 28 25" />
      <path
        class="outline"
        d="M92 54h128l-9 44a12 12 0 0 1-12 11H113a12 12 0 0 1-12-11z"
      />
      <circle class="solid" cx="120" cy="96" r="5" />
      <text
        class="solid"
        x="236"
        y="84"
        font-family="Anton, Impact, 'Arial Narrow', sans-serif"
        font-size="56"
        font-weight="900"
        letter-spacing="1"
      >Shoptify</text>
    </svg>
  `,
  styles: `
    :host {
      display: block;
    }

    svg {
      width: 100%;
      max-width: 360px;
      height: auto;
      display: block;
    }
  `,
})
export class Logotipo {}
