"""Imágenes que el Word necesita y que no existen como archivo: íconos de Font Awesome en cada
color, y las capturas dentro de su marco (ventana de navegador, teléfono o ventana de Excel).

Todo se guarda en caché en herramientas/.entorno/word/.
"""
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

MANUAL = Path(__file__).resolve().parents[2]
CACHE = MANUAL / 'herramientas' / '.entorno' / 'word'
FUENTES = MANUAL / 'fuentes' / 'ttf'
FA = FUENTES / 'fa-solid-900.ttf'

# Escala de las capturas: 1784 px (892 px CSS a doble densidad) llenan los 16 cm del texto.
PX_POR_CM = 1784 / 16


def hex_rgb(h):
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))


# ---------------------------------------------------------------------------
#  Íconos
# ---------------------------------------------------------------------------
def icono(codigo, color='102A3F', fondo=None, alto=96, margen=0.0):
    """PNG de un ícono de Font Awesome. Con `fondo`, el ícono va sobre un rectángulo de ese color
    (para los íconos dentro de botones sombreados)."""
    carpeta = CACHE / 'iconos'
    carpeta.mkdir(parents=True, exist_ok=True)
    destino = carpeta / f'{codigo}-{color}-{fondo or "x"}-{alto}-{margen}.png'
    if destino.exists():
        return destino
    fuente = ImageFont.truetype(str(FA), alto * 2)
    caracter = chr(int(codigo, 16))
    x0, y0, x1, y1 = fuente.getbbox(caracter)
    lienzo = Image.new('RGBA', (x1 - x0, y1 - y0), (0, 0, 0, 0))
    ImageDraw.Draw(lienzo).text((-x0, -y0), caracter, font=fuente, fill=hex_rgb(color) + (255,))
    # Todos los íconos con el mismo alto, como en el texto
    escala = alto / lienzo.height
    lienzo = lienzo.resize((max(1, round(lienzo.width * escala)), alto), Image.LANCZOS)
    if fondo or margen:
        m = round(alto * margen)
        base = Image.new('RGBA', (lienzo.width + 2 * m, alto + 2 * m), hex_rgb(fondo) + (255,) if fondo else (0, 0, 0, 0))
        base.alpha_composite(lienzo, (m, m))
        lienzo = base
    lienzo.save(destino)
    return destino


def cuadro_icono(codigo, lado=200):
    """Cuadro azul redondeado con el ícono en blanco (fichas de cada módulo)."""
    carpeta = CACHE / 'iconos'
    carpeta.mkdir(parents=True, exist_ok=True)
    destino = carpeta / f'cuadro-{codigo}-{lado}.png'
    if destino.exists():
        return destino
    base = Image.new('RGBA', (lado, lado), (0, 0, 0, 0))
    ImageDraw.Draw(base).rounded_rectangle((0, 0, lado - 1, lado - 1), radius=round(lado * 0.09), fill=hex_rgb('0072AF'))
    simbolo = Image.open(icono(codigo, 'FFFFFF', alto=round(lado * 0.42)))
    base.alpha_composite(simbolo, ((lado - simbolo.width) // 2, (lado - simbolo.height) // 2))
    base.save(destino)
    return destino


# ---------------------------------------------------------------------------
#  Marcos de las capturas
# ---------------------------------------------------------------------------
def _fuente(nombre, tam):
    return ImageFont.truetype(str(FUENTES / nombre), round(tam))


def _puntos(dibujo, x, y, k):
    for i, c in enumerate(('E06C66', 'F8C35A', '68C17C')):
        r = 5.5 * k
        cx = x + i * 20 * k
        dibujo.ellipse((cx - r, y - r, cx + r, y + r), fill=hex_rgb(c))


def _ventana(captura, barra, k):
    """Coloca la captura bajo una barra de título, con borde y esquinas superiores redondeadas."""
    borde = max(2, round(2 * k))
    alto_barra = barra.height
    ancho = captura.width + 2 * borde
    alto = captura.height + alto_barra + 2 * borde
    marco = Image.new('RGBA', (ancho, alto), (0, 0, 0, 0))
    d = ImageDraw.Draw(marco)
    radio = round(10 * k)
    d.rounded_rectangle((0, 0, ancho - 1, alto - 1), radius=radio, fill=hex_rgb('D5E2EB'), corners=(True, True, False, False))
    d.rounded_rectangle((borde, borde, ancho - 1 - borde, alto_barra + borde), radius=max(1, radio - borde),
                        fill=hex_rgb('EEF4F8'), corners=(True, True, False, False))
    marco.alpha_composite(barra.convert('RGBA'), (borde, borde))
    d.line((borde, alto_barra + borde, ancho - borde, alto_barra + borde), fill=hex_rgb('D5E2EB'), width=borde)
    marco.alpha_composite(captura.convert('RGBA'), (borde, alto_barra + borde + 0))
    return marco


def navegador(archivo, direccion):
    """Captura dentro de una ventana de navegador con la dirección en la barra."""
    destino = CACHE / 'marcos' / f'{archivo}.png'
    destino.parent.mkdir(parents=True, exist_ok=True)
    captura = Image.open(MANUAL / 'capturas' / f'{archivo}.png')
    k = max(1.0, captura.width / 1784) * 2  # tamaño de los elementos del marco (doble densidad)
    alto_barra = round(22 * k)
    barra = Image.new('RGBA', (captura.width, alto_barra), hex_rgb('EEF4F8') + (255,))
    d = ImageDraw.Draw(barra)
    _puntos(d, 14 * k, alto_barra / 2, k / 2 * 1.0)
    x_url = round(60 * k / 2 * 2)
    d.rounded_rectangle((x_url, round(4 * k), captura.width - round(10 * k), alto_barra - round(4 * k)),
                        radius=round(7 * k), fill=(255, 255, 255), outline=hex_rgb('D5E2EB'), width=max(1, round(k / 2)))
    candado = Image.open(icono('F023', '4A5A67', alto=round(7.5 * k)))
    barra.alpha_composite(candado, (x_url + round(8 * k), (alto_barra - candado.height) // 2))
    letra = _fuente('SourceSans3-Regular.ttf', 10.5 * k)
    d.text((x_url + round(8 * k) + candado.width + round(6 * k), alto_barra / 2), direccion, font=letra,
           fill=hex_rgb('4A5A67'), anchor='lm')
    _ventana(captura, barra, k / 2).save(destino)
    return destino


def ventana_excel(archivo, titulo):
    """Captura de la hoja de cálculo con una barra de ventana y el nombre del archivo."""
    destino = CACHE / 'marcos' / f'{archivo}.png'
    destino.parent.mkdir(parents=True, exist_ok=True)
    captura = Image.open(MANUAL / 'capturas' / f'{archivo}.png')
    k = max(1.0, captura.width / 1784) * 2
    alto_barra = round(22 * k)
    barra = Image.new('RGBA', (captura.width, alto_barra), hex_rgb('EEF4F8') + (255,))
    d = ImageDraw.Draw(barra)
    _puntos(d, 14 * k, alto_barra / 2, k / 2)
    hoja = Image.open(icono('F1C3', '1E7B4E', alto=round(9 * k)))
    x = round(66 * k)
    barra.alpha_composite(hoja, (x, (alto_barra - hoja.height) // 2))
    d.text((x + hoja.width + round(6 * k), alto_barra / 2), titulo, font=_fuente('SourceSans3-Semibold.ttf', 10.5 * k),
           fill=hex_rgb('4A5A67'), anchor='lm')
    _ventana(captura, barra, k / 2).save(destino)
    return destino


def telefono(archivo):
    """Captura de celular dentro de un marco de teléfono azul marino."""
    destino = CACHE / 'marcos' / f'{archivo}.png'
    destino.parent.mkdir(parents=True, exist_ok=True)
    captura = Image.open(MANUAL / 'capturas' / f'{archivo}.png').convert('RGBA')
    borde = round(captura.width * 0.045)
    arriba, abajo = round(borde * 2.6), round(borde * 1.9)
    ancho, alto = captura.width + 2 * borde, captura.height + arriba + abajo
    marco = Image.new('RGBA', (ancho, alto), (0, 0, 0, 0))
    d = ImageDraw.Draw(marco)
    d.rounded_rectangle((0, 0, ancho - 1, alto - 1), radius=round(borde * 3.2), fill=hex_rgb('0B2C47'))
    d.rounded_rectangle((borde, arriba, ancho - 1 - borde, alto - abajo), radius=round(borde * 0.6), fill=(255, 255, 255))
    marco.alpha_composite(captura, (borde, arriba))
    cx, cy = ancho // 2, arriba // 2
    d.rounded_rectangle((cx - borde * 2.2, cy - borde * 0.22, cx + borde * 2.2, cy + borde * 0.22), radius=borde * 0.22,
                        fill=hex_rgb('8F9392'))
    marco.save(destino)
    return destino


def ancho_cm(png, maximo=16.0, alto_max=14.4):
    """Ancho en cm con el que la imagen respeta la escala común de las capturas (y los límites)."""
    img = Image.open(png)
    ancho = img.width / PX_POR_CM
    alto = img.height / PX_POR_CM
    factor = min(1.0, maximo / ancho, alto_max / alto)
    return ancho * factor


def ancho_figura_cm(png, dpi=300, maximo=16.0):
    img = Image.open(png)
    return min(maximo, img.width / dpi * 2.54)


if __name__ == '__main__':
    print(navegador('02-01-login', 'cotizaciones.trainingcorporation.com.co/login'))
    print(navegador('03-01-cotizaciones-lista', 'cotizaciones.trainingcorporation.com.co/dashboard/ordenes-cotizacion'))
    print(ventana_excel('03-07-excel-clientes', 'clientes_03-11-2026_08-02AM.xlsx'))
    print(telefono('02-09-menu-movil'))
    print(cuadro_icono('F084'))
    print(icono('F303', '0072AF'))
