import { Recibo } from './comprador.service';

const ANCHO_PAGINA = 595.28;
const ALTO_PAGINA = 841.89;

const CP1252: Record<number, number> = {
  0x20ac: 0x80, 0x201a: 0x82, 0x0192: 0x83, 0x201e: 0x84,
  0x2026: 0x85, 0x2020: 0x86, 0x2021: 0x87, 0x02c6: 0x88,
  0x2030: 0x89, 0x0160: 0x8a, 0x2039: 0x8b, 0x0152: 0x8c,
  0x017d: 0x8e, 0x2018: 0x91, 0x2019: 0x92, 0x201c: 0x93,
  0x201d: 0x94, 0x2022: 0x95, 0x2013: 0x96, 0x2014: 0x97,
  0x02dc: 0x98, 0x2122: 0x99, 0x0161: 0x9a, 0x203a: 0x9b,
  0x0153: 0x9c, 0x017e: 0x9e, 0x0178: 0x9f,
};

function aWinAnsi(texto: string): string {
  let salida = '';
  for (const caracter of texto) {
    const codigo = caracter.codePointAt(0) ?? 0;
    let byte: number;
    if (codigo >= 0x20 && codigo <= 0x7e) {
      byte = codigo;
    } else if (codigo >= 0xa1 && codigo <= 0xff) {
      byte = codigo;
    } else if (CP1252[codigo] !== undefined) {
      byte = CP1252[codigo];
    } else {
      byte = 0x3f;
    }
    salida += String.fromCharCode(byte);
  }
  return salida;
}

function escapar(texto: string): string {
  return texto.replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
}

function cortar(texto: string, maximo: number): string {
  return texto.length > maximo ? texto.slice(0, maximo - 1) + '…' : texto;
}

function formatearPrecio(precio: string | number): string {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
  }).format(Number(precio ?? 0));
}

function formatearFecha(fecha: string): string {
  return new Date(fecha).toLocaleString('es-MX', {
    dateStyle: 'long',
    timeStyle: 'short',
  });
}

export function generarPdfRecibo(recibo: Recibo): Uint8Array<ArrayBuffer> {
  const lineas: string[] = [];

  const dibujar = (x: number, yDesdeArriba: number, texto: string, tam: number, negrita: boolean): void => {
    const y = ALTO_PAGINA - yDesdeArriba;
    const fuente = negrita ? 'F2' : 'F1';
    lineas.push(`BT /${fuente} ${tam} Tf 1 0 0 1 ${x} ${y} Tm (${escapar(aWinAnsi(texto))}) Tj ET`);
  };

  const dibujarSeparador = (yDesdeArriba: number): void => {
    const y = ALTO_PAGINA - yDesdeArriba;
    lineas.push(`0.75 0.75 0.75 RG`);
    lineas.push(`${40} ${y} m ${ANCHO_PAGINA - 40} ${y} l S`);
  };

  let y = 44;

  dibujar(40, y, 'SHOPTIFY', 24, true);
  y += 34;
  dibujar(40, y, 'Comprobante de pedido', 11, false);
  y += 34;
  dibujar(40, y, `Folio: ${recibo.numero_pedido}`, 13, true);
  y += 22;
  dibujar(40, y, formatearFecha(recibo.fecha_pedido), 10, false);
  y += 18;
  dibujar(40, y, `Estado del pedido: ${recibo.estado}`, 10, false);
  y += 16;
  dibujarSeparador(y);
  y += 20;

  dibujar(40, y, 'COMPRADOR', 9.5, true);
  y += 18;
  dibujar(40, y, recibo.persona, 10, false);
  y += 24;

  dibujar(40, y, 'DIRECCIÓN DE ENVÍO', 9.5, true);
  y += 18;
  const direccion = recibo.direccion;
  dibujar(
    40,
    y,
    `${direccion.nombre} — ${direccion.calle} ${direccion.numero_exterior}` +
      (direccion.numero_interior ? ` int. ${direccion.numero_interior}` : ''),
    10,
    false,
  );
  y += 18;
  dibujar(40, y, `${direccion.colonia}, ${direccion.municipio}, ${direccion.estado}`, 10, false);
  y += 18;
  dibujar(40, y, `CP ${direccion.codigo_postal} · ${direccion.pais}`, 10, false);
  y += 26;
  dibujarSeparador(y);
  y += 18;

  const columnas: Array<{ etiqueta: string; x: number }> = [
    { etiqueta: 'Identificador', x: 40 },
    { etiqueta: 'Producto', x: 100 },
    { etiqueta: 'Cantidad', x: 270 },
    { etiqueta: 'Precio unitario', x: 320 },
    { etiqueta: 'Total del producto', x: 415 },
    { etiqueta: 'Vendedor', x: 500 },
  ];

  for (const columna of columnas) {
    dibujar(columna.x, y, columna.etiqueta, 9, true);
  }
  y += 18;

  for (const item of recibo.detalle) {
    dibujar(40, y, cortar(item.identificador, 8), 9, false);
    dibujar(100, y, cortar(item.nombre, 30), 9, false);
    dibujar(270, y, String(item.cantidad), 9, false);
    dibujar(320, y, formatearPrecio(item.precio_unitario), 9, false);
    dibujar(415, y, formatearPrecio(item.subtotal), 9, false);
    dibujar(500, y, cortar(item.vendedor, 11), 9, false);
    y += 18;
  }

  y += 6;
  dibujar(415, y, 'Subtotal', 10, true);
  dibujar(500, y, formatearPrecio(recibo.total), 10, false);
  y += 20;
  dibujar(415, y, 'Total', 12, true);
  dibujar(500, y, formatearPrecio(recibo.total), 12, true);
  y += 40;

  dibujar(40, ALTO_PAGINA - 56, 'Gracias por tu compra.', 10, false);

  const contenido = lineas.join('\n');

  const objetos: string[] = [
    '1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj',
    '2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj',
    `3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${ANCHO_PAGINA} ${ALTO_PAGINA}] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj`,
    '4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj',
    '5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj',
    `6 0 obj\n<< /Length ${contenido.length} >>\nstream\n${contenido}\nendstream\nendobj`,
  ];

  let pdf = '%PDF-1.4\n';
  const offsets: number[] = [];
  for (const objeto of objetos) {
    offsets.push(pdf.length);
    pdf += objeto + '\n';
  }

  const posicionXref = pdf.length;
  pdf += `xref\n0 ${objetos.length + 1}\n`;
  pdf += '0000000000 65535 f \n';
  for (const offset of offsets) {
    pdf += offset.toString().padStart(10, '0') + ' 00000 n \n';
  }
  pdf += `trailer\n<< /Size ${objetos.length + 1} /Root 1 0 R >>\nstartxref\n${posicionXref}\n%%EOF`;

  const bytes = new Uint8Array(pdf.length);
  for (let i = 0; i < pdf.length; i++) {
    bytes[i] = pdf.charCodeAt(i) & 0xff;
  }

  return bytes;
}
