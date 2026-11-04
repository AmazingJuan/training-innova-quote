"""Lector de los fuentes LaTeX del manual.

Entiende solo el subconjunto de LaTeX que usa este manual (los comandos de estilo.tex y de los
capítulos) y lo devuelve como una lista de bloques con texto enriquecido, que word.py convierte
en un documento de Word.
"""
import re
from dataclasses import dataclass, field, replace
from pathlib import Path

MANUAL = Path(__file__).resolve().parents[2]

# Colores de estilo.tex
COLORES = {
    'azul': '0072AF', 'azulhondo': '005A8C', 'azulvivo': '1391DA', 'cielo': '7FD0F5', 'noche': '0B2C47',
    'tinta': '102A3F', 'pizarra': '4A5A67', 'grismarca': '8F9392', 'niebla': 'A9C6DC', 'nube': 'EEF4F8',
    'linea': 'D5E2EB', 'error': 'B3261E', 'ambarfondo': 'FFFBEB', 'ambarborde': 'FDE68A', 'ambartexto': '78350F',
    'verdefondo': 'ECFDF5', 'verdeborde': 'A7F3D0', 'verdetexto': '065F46', 'verde': '1E7B4E', 'errorfondo': 'FBEDEC',
    'bruma': '93ABC0', 'white': 'FFFFFF', 'black': '000000',
}

# Tamaños de LaTeX para un documento de 11 pt
TAMANOS = {'tiny': 6, 'scriptsize': 8, 'footnotesize': 9, 'small': 10, 'normalsize': 11, 'large': 12, 'Large': 14.4, 'LARGE': 17.3}

ICONOS = {
    'icVer': 'F06E', 'icEditar': 'F303', 'icEliminar': 'F2ED', 'icMas': '2B', 'icBuscar': 'F002', 'icCerrar': 'F00D',
    'icSubir': 'F062', 'icBajar': 'F063', 'icAtras': 'F060', 'icAnterior': 'F053', 'icSiguiente': 'F054',
    'icSalir': 'F2F5', 'icExterno': 'F08E', 'icExcel': 'F1C3', 'icCargar': 'F093', 'icDescargar': 'F019',
    'icPlay': 'F04B', 'icCopiar': 'F0C5', 'icCorreo': 'F0E0', 'icEnviar': 'F1D8', 'icAjustes': 'F013',
    'icUsuarios': 'F0C0', 'icMaletin': 'F0B1', 'icRama': 'F126', 'icLlave': 'F0AD', 'icArbol': 'F0E8',
    'icCotizacion': 'F70E', 'icContactos': 'F2B9', 'icMenu': 'F0C9', 'icOk': 'F058', 'icCandado': 'F023',
    'icCasa': 'F015', 'icGlobo': 'F0AC', 'icClave': 'F084', 'icFlechaRama': 'F149', 'icMovil': 'F3CD',
    'icDuplicar': 'F24D',
}

# Recuadros de aviso: color del borde, fondo, ícono y título por defecto
AVISOS = {
    'nota': ('azul', 'nube', 'F05A', 'Nota'),
    'importante': ('error', 'errorfondo', 'F071', 'Importante'),
    'consejo': ('azulvivo', 'EBF7FD', 'F0EB', 'Consejo'),
    'resultado': ('verde', 'verdefondo', 'F058', 'Resultado'),
}

# Insignias: fondo, borde, texto
INSIGNIAS = {
    'pendiente': ('ambarfondo', 'ambarborde', 'ambartexto', 'Pendiente'),
    'generada': ('verdefondo', 'verdeborde', 'verdetexto', 'Generada'),
    'vista': ('nube', 'linea', 'azulhondo', 'Vista'),
    'novista': ('nube', 'linea', 'tinta', 'No vista'),
}


def color(nombre):
    nombre = nombre.strip()
    if nombre in COLORES:
        return COLORES[nombre]
    m = re.fullmatch(r'(\w+)!(\d+)!white', nombre)
    if m:  # mezcla con blanco, como xcolor
        base, pct = COLORES[m.group(1)], int(m.group(2)) / 100
        return ''.join(f'{round(int(base[i:i + 2], 16) * pct + 255 * (1 - pct)):02X}' for i in (0, 2, 4))
    if re.fullmatch(r'[0-9A-Fa-f]{6}', nombre):
        return nombre.upper()
    raise KeyError(f'color desconocido: {nombre}')


# ---------------------------------------------------------------------------
#  Modelo
# ---------------------------------------------------------------------------
@dataclass(frozen=True)
class Fmt:
    peso: str = 'normal'        # normal · sb · b · eb · ub
    italica: bool = False
    mono: bool = False
    display: bool = False       # Montserrat
    color: str | None = None
    tamano: float | None = None
    caja: tuple | None = None   # ('boton'|'botonsec'|'botonrojo'|'campo'|'tecla') o ('insignia', fondo, borde, texto)


@dataclass
class Texto:
    texto: str
    fmt: Fmt


@dataclass
class Icono:
    codigo: str
    fmt: Fmt


@dataclass
class Enlace:
    destino: str
    contenido: list


@dataclass
class Salto:
    pass


@dataclass
class Bloque:
    tipo: str
    datos: dict = field(default_factory=dict)


# ---------------------------------------------------------------------------
#  Utilidades de lectura
# ---------------------------------------------------------------------------
def sin_comentarios(texto):
    # Como TeX: un comentario también se come el salto de línea y la sangría de la línea siguiente.
    return re.sub(r'(?<!\\)%[^\n]*\n[ \t]*', '', texto)


def fin_grupo(s, i, abre='{', cierra='}'):
    """Índice justo después del grupo que empieza en s[i] == abre."""
    nivel = 0
    while i < len(s):
        c = s[i]
        if c == '\\':
            i += 2
            continue
        if c == abre:
            nivel += 1
        elif c == cierra:
            nivel -= 1
            if nivel == 0:
                return i + 1
        i += 1
    raise ValueError(f'grupo sin cerrar: {s[max(0, i - 80):i][:80]!r}')


def saltar_espacios(s, i):
    while i < len(s) and s[i] in ' \t\n':
        i += 1
    return i


def leer_arg(s, i):
    """(contenido, índice siguiente) del argumento obligatorio en s[i:]."""
    i = saltar_espacios(s, i)
    if s[i] == '{':
        f = fin_grupo(s, i)
        return s[i + 1:f - 1], f
    if s[i] == '\\':
        m = re.match(r'(?s)\\([A-Za-z]+|.)', s[i:])
        return m.group(0), i + len(m.group(0))
    return s[i], i + 1


def leer_opt(s, i):
    """(contenido o None, índice) del argumento opcional [..] en s[i:]."""
    j = saltar_espacios(s, i)
    if j < len(s) and s[j] == '[':
        f = fin_grupo(s, j, '[', ']')
        return s[j + 1:f - 1], f
    return None, i


def dividir_items(s):
    """Divide el cuerpo de una lista por sus \\item de primer nivel (no los de listas anidadas)."""
    partes, nivel, inicio, i = [], 0, None, 0
    patron = re.compile(r'\\(begin|end)\{[^}]*\}|\\item\b')
    for m in patron.finditer(s):
        if m.group(0).startswith('\\begin'):
            nivel += 1
        elif m.group(0).startswith('\\end'):
            nivel -= 1
        elif nivel == 0:
            if inicio is not None:
                partes.append(s[inicio:m.start()])
            inicio = m.end()
    if inicio is not None:
        partes.append(s[inicio:])
    return partes


def dividir(s, sep):
    """Divide s por el separador sep (texto) solo en el nivel superior de llaves."""
    partes, nivel, inicio, i = [], 0, 0, 0
    while i < len(s):
        c = s[i]
        if c == '\\' and not s.startswith(sep, i):
            i += 2
            continue
        if c == '{':
            nivel += 1
        elif c == '}':
            nivel -= 1
        elif nivel == 0 and s.startswith(sep, i):
            partes.append(s[inicio:i])
            i += len(sep)
            inicio = i
            continue
        i += 1
    partes.append(s[inicio:])
    return partes


# ---------------------------------------------------------------------------
#  Referencias (del .aux del PDF)
# ---------------------------------------------------------------------------
def leer_etiquetas():
    aux = MANUAL / 'manual-administrador.aux'
    etiquetas = {}
    for linea in aux.read_text(encoding='utf-8').splitlines():
        if linea.startswith('\\newlabel{'):
            nombre, i = leer_arg(linea, len('\\newlabel'))
            valor, _ = leer_arg(linea, i)
            numero, _ = leer_arg(valor, 0)
            etiquetas[nombre] = numero
    return etiquetas


# ---------------------------------------------------------------------------
#  Texto en línea
# ---------------------------------------------------------------------------
class Lector:
    def __init__(self):
        principal = (MANUAL / 'manual-administrador.tex').read_text(encoding='utf-8')
        self.datos = {k: v for k, v in re.findall(r'\\newcommand\{\\(versiondoc|fechadoc|elaboradopor|dominio)\}\{(.*)\}', principal)}
        self.datos['dominio'] = re.sub(r'\\allowbreak\s*', '', self.datos['dominio'])
        self.etiquetas = leer_etiquetas()
        self.capitulo = 0
        self.seccion = 0
        self.subseccion = 0
        self.apendice = False

    # ---- referencias -------------------------------------------------------
    def numero(self, etiqueta):
        if etiqueta not in self.etiquetas:
            raise KeyError(f'etiqueta sin número: {etiqueta}')
        return self.etiquetas[etiqueta]

    # ---- en línea ------------------------------------------------------------
    def en_linea(self, s, fmt=Fmt()):
        """Convierte texto LaTeX (sin bloques) en una lista de Texto / Icono / Enlace / Salto."""
        s = re.sub(r'[ \t\n]+', ' ', s)   # como TeX: varios espacios valen por uno
        salida = []
        texto = []

        def volcar():
            if texto:
                t = ''.join(texto)
                if salida and isinstance(salida[-1], Texto) and salida[-1].fmt == fmt:
                    salida[-1].texto += t
                else:
                    salida.append(Texto(t, fmt))
                texto.clear()

        i = 0
        while i < len(s):
            c = s[i]
            if c == '{':
                f = fin_grupo(s, i)
                volcar()
                salida += self.en_linea(s[i + 1:f - 1], fmt)
                i = f
            elif c == '}':
                i += 1
            elif c == '~':
                texto.append('\u00a0')
                i += 1
            elif c == '\n' or c == '\t':
                texto.append(' ')
                i += 1
            elif s.startswith('---', i):
                texto.append('—')
                i += 3
            elif s.startswith('--', i):
                texto.append('–')
                i += 2
            elif c == '\\':
                m = re.match(r'(?s)\\([A-Za-z]+\*?|.)', s[i:])
                nombre = m.group(1)
                i += len(m.group(0))
                if nombre == '\\':                        # salto de línea  \\  o \\[3pt]
                    _, i = leer_opt(s, i)
                    volcar()
                    salida.append(Salto())
                    continue
                if not nombre[0].isalpha():
                    texto.append({',': '\u202f', ' ': ' ', '%': '%', '&': '&', '_': '_', '#': '#', '$': '$',
                                  '{': '{', '}': '}', '-': ''}.get(nombre, nombre))
                    continue
                if nombre in ('ref', 'ref*'):
                    etiqueta, i = leer_arg(s, i)
                    texto.append(self.numero(etiqueta))
                    continue
                if nombre in self.datos:
                    texto.append(self.datos[nombre])
                    if i < len(s) and s[i] == ' ' and nombre == 'dominio':
                        pass
                    continue
                if nombre in ('textbullet', 'textbar', 'textperiodcentered', 'ldots', 'dots', 'allowbreak', 'quad'):
                    texto.append({'textbullet': '•', 'textbar': '|', 'textperiodcentered': '·', 'ldots': '…',
                                  'dots': '…', 'allowbreak': '\u200b', 'quad': ' '}[nombre])
                    # Los comandos de palabra se comen el espacio que los sigue
                    if nombre not in ('allowbreak',) and i < len(s) and s[i] == ' ':
                        i += 1
                    continue
                volcar()
                i = self.comando(nombre, s, i, fmt, salida)
                # Las declaraciones (\small, \color…) afectan al resto del grupo
                if isinstance(i, tuple):
                    i, fmt = i
            else:
                texto.append(c)
                i += 1
        volcar()
        return salida

    def comando(self, nombre, s, i, fmt, salida):
        """Procesa \\nombre en s[i:]. Devuelve el índice siguiente, o (índice, nuevo fmt) si es una declaración."""
        def arg():
            nonlocal i
            a, i = leer_arg(s, i)
            return a

        def opt():
            nonlocal i
            o, i = leer_opt(s, i)
            return o

        def agrega(contenido, nuevo):
            salida.extend(self.en_linea(contenido, nuevo))

        # ---- declaraciones -------------------------------------------------
        if nombre in TAMANOS:
            return i, replace(fmt, tamano=TAMANOS[nombre])
        if nombre == 'color':
            return (lambda c: (i, replace(fmt, color=color(c))))(arg())
        if nombre == 'montserrat':
            return i, replace(fmt, display=True)
        if nombre == 'bfseries':
            return i, replace(fmt, peso='b')
        if nombre == 'fontseries':
            serie = arg()
            return i, replace(fmt, peso={'sb': 'sb', 'b': 'b', 'bx': 'b', 'eb': 'eb', 'ub': 'ub', 'm': 'normal'}[serie])
        if nombre == 'upshape':
            return i, replace(fmt, italica=False)
        if nombre == 'itshape':
            return i, replace(fmt, italica=True)
        if nombre == 'ttfamily':
            return i, replace(fmt, mono=True)
        if nombre == 'fontsize':
            tam = arg()
            arg()
            return i, replace(fmt, tamano=float(tam))
        if nombre in ('selectfont', 'relax', 'noindent', 'centering', 'par', 'normalfont', 'hfill', 'null'):
            return i
        if nombre in ('vspace', 'vspace*', 'label', 'addfontfeatures', 'enlargethispage', 'markboth'):
            arg()
            if nombre == 'markboth':
                arg()
            return i
        if nombre in ('hspace', 'hspace*'):
            arg()
            salida.append(Texto(' ', fmt))
            return i
        # ---- formato --------------------------------------------------------
        if nombre == 'textbf':
            agrega(arg(), replace(fmt, peso='b'))
        elif nombre in ('textit', 'emph'):
            agrega(arg(), replace(fmt, italica=True))
        elif nombre == 'texttt':
            agrega(arg(), replace(fmt, mono=True))
        elif nombre == 'textcolor':
            c = arg()
            agrega(arg(), replace(fmt, color=color(c)))
        elif nombre == 'msg':
            contenido = arg()
            salida.append(Texto('«', fmt))
            agrega(contenido, replace(fmt, italica=True))
            salida.append(Texto('»', fmt))
        elif nombre == 'var':
            contenido = arg()
            f = replace(fmt, italica=False, color=COLORES['azulvivo'])
            salida.append(Texto('‹', f))
            agrega(contenido, f)
            salida.append(Texto('›', f))
        elif nombre == 'ruta':
            agrega(arg(), replace(fmt, mono=True, color=COLORES['azulhondo'], tamano=TAMANOS['small']))
        elif nombre == 'menu':
            a, b = arg(), arg()
            f = replace(fmt, peso='sb', color=COLORES['azulhondo'])
            agrega(a, f)
            salida.append(Texto('\u00a0›\u00a0', f))
            agrega(b, f)
        elif nombre in ('boton', 'botonsec', 'botonrojo', 'campo', 'tecla'):
            agrega(arg(), replace(fmt, caja=(nombre,)))
        elif nombre in INSIGNIAS:
            fondo, borde, letra, texto = INSIGNIAS[nombre]
            salida.append(Texto(texto, replace(fmt, caja=('insignia', COLORES[fondo], COLORES[borde], COLORES[letra]))))
        elif nombre in ('insigniainfo', 'insignianeutra'):
            letra = 'azulhondo' if nombre == 'insigniainfo' else 'tinta'
            agrega(arg(), replace(fmt, caja=('insignia', COLORES['nube'], COLORES['linea'], COLORES[letra])))
        elif nombre == 'insignia':
            o = opt() or ''
            props = dict(p.split('=') for p in [x.strip() for x in o.split(',')] if '=' in p)
            agrega(arg(), replace(fmt, caja=('insignia', color(props['colback']), color(props['colframe']), color(props['coltext']))))
        # ---- íconos ---------------------------------------------------------
        elif nombre == 'icono':
            c = opt() or 'azul'
            agrega(arg(), replace(fmt, color=color(c)))
        elif nombre == 'ico':
            salida.append(Icono(arg(), fmt))
        elif nombre in ICONOS:
            salida.append(Icono(ICONOS[nombre], fmt))
            if i < len(s) and s[i] == ' ':
                i += 1
        elif nombre == 'iconos':
            m = re.match(r'\s*\\char"([0-9A-Fa-f]+)(\\relax)?', s[i:])
            if m:
                salida.append(Icono(m.group(1).upper(), fmt))
                i += len(m.group(0))
        # ---- referencias ----------------------------------------------------
        elif nombre in ('seccion', 'capref', 'Capref', 'apref', 'Apref', 'figref', 'caprefclaro'):
            etiqueta = arg()
            palabra = {'seccion': 'sección', 'capref': 'capítulo', 'Capref': 'Capítulo', 'apref': 'apéndice',
                       'Apref': 'Apéndice', 'figref': 'figura', 'caprefclaro': 'capítulo'}[nombre]
            tono = COLORES['cielo'] if nombre == 'caprefclaro' else COLORES['azulhondo']
            salida.append(Enlace(etiqueta, [Texto(f'{palabra}\u00a0{self.numero(etiqueta)}', replace(fmt, peso='sb', color=tono))]))
        elif nombre == 'hyperref':
            etiqueta = opt()
            salida.append(Enlace(etiqueta, self.en_linea(arg(), fmt)))
        else:
            raise ValueError(f'comando en línea no soportado: \\{nombre} en {s[max(0, i - 60):i + 40]!r}')
        return i

    # ---------------------------------------------------------------------------
    #  Bloques
    # ---------------------------------------------------------------------------
    BLOQUES_ENTORNO = ('itemize', 'pasos', 'zonas', 'nota', 'importante', 'consejo', 'resultado', 'tabla', 'longtblr',
                       'figure', 'center', 'tcolorbox')
    COMANDOS_BLOQUE = ('chapter', 'chapter*', 'section', 'section*', 'subsection', 'subsubsection', 'entradilla',
                       'ficha', 'captura', 'capturaconnotas', 'capturamovil', 'capturaventana', 'faq', 'newcommand',
                       'tableofcontents', 'listoffigures', 'portada', 'contraportada', 'appendix', 'apendicestrue',
                       'pagenumbering', 'clearpage', 'input', 'markboth', 'enlargethispage', 'label', 'definecolor')

    def bloques(self, s):
        s = sin_comentarios(s)
        salida = []
        parrafo = []
        i = 0

        def cerrar_parrafo():
            texto = ''.join(parrafo).strip()
            parrafo.clear()
            if texto:
                contenido = self.en_linea(texto)
                if any(not (isinstance(x, Texto) and not x.texto.strip()) for x in contenido):
                    salida.append(Bloque('parrafo', {'contenido': contenido}))

        while i < len(s):
            # Párrafo nuevo en línea en blanco
            m = re.match(r'\n[ \t]*\n', s[i:])
            if m:
                cerrar_parrafo()
                i += len(m.group(0))
                continue
            if s.startswith('\\begin{', i):
                entorno = s[i + 7:s.index('}', i)]
                if entorno in self.BLOQUES_ENTORNO:
                    cerrar_parrafo()
                    i = self.entorno(entorno, s, i, salida)
                    continue
            if s.startswith('\\par', i) and not s[i + 4:i + 5].isalpha():
                cerrar_parrafo()
                i += 4
                continue
            m = re.match(r'\\([A-Za-z]+\*?)', s[i:])
            if m and m.group(1) in self.COMANDOS_BLOQUE:
                cerrar_parrafo()
                i = self.comando_bloque(m.group(1), s, i + len(m.group(0)), salida)
                continue
            # \vspace entre bloques no aporta nada en Word
            m = re.match(r'\\vspace\*?\{[^}]*\}', s[i:])
            if m and not ''.join(parrafo).strip():
                i += len(m.group(0))
                continue
            if s[i] == '{':
                f = fin_grupo(s, i)
                parrafo.append(s[i:f])
                i = f
                continue
            if s[i] == '\\':
                m = re.match(r'(?s)\\([A-Za-z]+|.)', s[i:])
                parrafo.append(m.group(0))
                i += len(m.group(0))
                continue
            parrafo.append(s[i])
            i += 1
        cerrar_parrafo()
        return salida

    def comando_bloque(self, nombre, s, i, salida):
        def arg():
            nonlocal i
            a, i = leer_arg(s, i)
            return a

        def opt():
            nonlocal i
            o, i = leer_opt(s, i)
            return o

        def etiqueta():
            nonlocal i
            m = re.match(r'\s*\\label\{([^}]*)\}', s[i:])
            if m:
                i += len(m.group(0))
                return m.group(1)
            return None

        if nombre in ('chapter', 'chapter*'):
            titulo = arg()
            et = etiqueta()
            numero = None
            if nombre == 'chapter':
                self.capitulo += 1
                self.seccion = self.subseccion = 0
                numero = chr(64 + self.capitulo) if self.apendice else str(self.capitulo)
                if et and self.numero(et) != numero:
                    raise ValueError(f'numeración distinta al PDF en {titulo}')
            salida.append(Bloque('capitulo', {'titulo': self.en_linea(titulo), 'texto': titulo_plano(titulo),
                                              'numero': numero, 'apendice': self.apendice, 'etiqueta': et}))
        elif nombre in ('section', 'section*', 'subsection', 'subsubsection'):
            titulo = arg()
            et = etiqueta()
            nivel = {'section': 2, 'section*': 2, 'subsection': 3, 'subsubsection': 4}[nombre]
            numero = None
            cap = chr(64 + self.capitulo) if self.apendice else str(self.capitulo)
            if nombre == 'section':
                self.seccion += 1
                self.subseccion = 0
                numero = f'{cap}.{self.seccion}'
            elif nombre == 'subsection':
                self.subseccion += 1
                numero = f'{cap}.{self.seccion}.{self.subseccion}'
            if numero and et and self.numero(et) != numero:
                raise ValueError(f'numeración distinta al PDF en {titulo}: {numero} / {self.numero(et)}')
            salida.append(Bloque('titulo', {'nivel': nivel, 'titulo': self.en_linea(titulo), 'texto': titulo_plano(titulo),
                                            'numero': numero, 'etiqueta': et}))
        elif nombre == 'label':
            arg()
        elif nombre == 'entradilla':
            salida.append(Bloque('entradilla', {'contenido': self.en_linea(arg())}))
        elif nombre == 'ficha':
            icono, ubicacion, ruta, permite = arg(), arg(), arg(), arg()
            salida.append(Bloque('ficha', {'icono': icono, 'ubicacion': self.en_linea(ubicacion),
                                           'ruta': self.datos['dominio'] + ruta, 'permite': self.en_linea(permite)}))
        elif nombre in ('captura', 'capturaconnotas', 'capturamovil', 'capturaventana'):
            opt()
            archivo = arg()
            if nombre == 'capturamovil':
                ruta = None
            else:
                ruta = arg()
            pie, _que = arg(), arg()
            notas = self.bloques(arg()) if nombre == 'capturaconnotas' else []
            et = 'fig:' + archivo
            salida.append(Bloque('captura', {'tipo': nombre, 'archivo': archivo, 'ruta': ruta,
                                             'pie': self.en_linea(pie), 'pie_texto': titulo_plano(pie),
                                             'numero': self.numero(et), 'etiqueta': et, 'notas': notas}))
        elif nombre == 'faq':
            pregunta, respuesta = arg(), arg()
            salida.append(Bloque('faq', {'pregunta': self.en_linea(pregunta), 'respuesta': self.bloques(respuesta)}))
        elif nombre == 'newcommand':
            arg()
            opt()
            arg()
        elif nombre in ('tableofcontents', 'listoffigures', 'portada', 'contraportada'):
            salida.append(Bloque(nombre))
        elif nombre == 'appendix':
            self.apendice = True
            self.capitulo = 0
        elif nombre in ('apendicestrue', 'clearpage'):
            pass
        elif nombre == 'pagenumbering':
            salida.append(Bloque('numeracion', {'formato': arg()}))
        elif nombre == 'input':
            archivo = MANUAL / (arg() + '.tex')
            salida.extend(self.bloques(archivo.read_text(encoding='utf-8')))
        elif nombre == 'markboth':
            arg()
            arg()
        elif nombre == 'enlargethispage':
            arg()
        elif nombre == 'definecolor':
            arg()
            arg()
            arg()
        return i

    def entorno(self, entorno, s, i, salida):
        inicio = i + len('\\begin{' + entorno + '}')
        # Fin del entorno, respetando los entornos anidados del mismo nombre
        nivel, j = 1, inicio
        patron = re.compile(r'\\(begin|end)\{' + re.escape(entorno) + r'\}')
        while nivel:
            m = patron.search(s, j)
            nivel += 1 if m.group(1) == 'begin' else -1
            j = m.end()
        cuerpo = s[inicio:m.start()]
        fin = j

        if entorno in ('itemize', 'pasos', 'zonas'):
            o, k = leer_opt(cuerpo, 0)
            cuerpo = cuerpo[k:]
            marca = 'check' if o and 'F00C' in o else None
            items = []
            for parte in dividir_items(cuerpo):
                items.append(self.bloques(parte))
            salida.append(Bloque('lista', {'tipo': entorno, 'items': items, 'continua': o == 'resume', 'marca': marca}))
        elif entorno in AVISOS:
            o, k = leer_opt(cuerpo, 0)
            borde, fondo, icono, titulo = AVISOS[entorno]
            salida.append(Bloque('aviso', {'tipo': entorno, 'titulo': o or titulo, 'borde': color(borde),
                                           'fondo': color(fondo), 'icono': icono, 'contenido': self.bloques(cuerpo[k:])}))
        elif entorno in ('tabla', 'longtblr'):
            spec, k = leer_arg(cuerpo, 0)
            columnas = leer_colspec(spec)
            filas = []
            for fila in dividir(cuerpo[k:], '\\\\'):
                fila = re.sub(r'^\s*\[[^\]]*\]', '', fila)
                if not fila.strip():
                    continue
                celdas = []
                for celda in dividir(fila, '&'):
                    celda = celda.strip()
                    union, fondo = 1, None
                    m = re.match(r'\\SetCell\[c=(\d+)\]\{([^}]*)\}', celda)
                    if m:
                        union = int(m.group(1))
                        props = dict(p.strip().split('=') for p in m.group(2).split(',') if '=' in p)
                        fondo = color(props['bg']) if 'bg' in props else None
                        celda = celda[len(m.group(0)):].strip()
                    celdas.append({'contenido': self.en_linea(celda), 'union': union, 'fondo': fondo})
                # Las celdas vacías que siguen a una unida no existen en Word
                limpias, saltar = [], 0
                for c in celdas:
                    if saltar:
                        saltar -= 1
                        continue
                    limpias.append(c)
                    saltar = c['union'] - 1
                filas.append(limpias)
            salida.append(Bloque('tabla', {'columnas': columnas, 'filas': filas}))
        elif entorno == 'figure':
            et = re.search(r'\\label\{(fig:[^}]*)\}', cuerpo).group(1)
            pie = leer_arg(cuerpo, cuerpo.index('\\caption') + len('\\caption'))[0]
            salida.append(Bloque('figura', {'archivo': et[4:], 'pie': self.en_linea(pie), 'pie_texto': titulo_plano(pie),
                                            'numero': self.numero(et), 'etiqueta': et}))
        elif entorno == 'center':
            ini = cuerpo.index('\\begin{tabular}')
            _, k = leer_arg(cuerpo, ini + len('\\begin{tabular}'))
            interior = cuerpo[k:cuerpo.index('\\end{tabular}')]
            filas = [[self.en_linea(c.strip()) for c in dividir(f, '&')] for f in dividir(interior, '\\\\') if f.strip()]
            tamano = TAMANOS['small'] if '\\small' in cuerpo[:ini] else None
            salida.append(Bloque('columnas', {'filas': filas, 'tamano': tamano}))
        elif entorno == 'tcolorbox':
            o, k = leer_opt(cuerpo, 0)
            tipo = 'oscuro' if 'colback=noche' in o else 'resumen'
            salida.append(Bloque('caja', {'tipo': tipo, 'contenido': self.bloques(cuerpo[k:])}))
        return fin


def leer_colspec(spec):
    m = re.search(r'colspec=\{(.*)\}', spec)
    columnas = []
    for tipo, props in re.findall(r'([QX])\[([^\]]*)\]', m.group(1)):
        partes = [p.strip() for p in props.split(',')]
        ancho = next((float(p[:-2]) for p in partes if p.endswith('cm')), None)
        alineado = 'centro' if 'c' in partes else 'izquierda'
        columnas.append({'ancho': ancho, 'alineado': alineado, 'medio': 'm' in partes})
    return columnas


def titulo_plano(s):
    """Texto sin formato (para marcadores, índices y encabezados)."""
    lector = Lector.__new__(Lector)
    lector.datos = LECTOR_GLOBAL.datos if LECTOR_GLOBAL else {}
    lector.etiquetas = LECTOR_GLOBAL.etiquetas if LECTOR_GLOBAL else {}
    partes = []
    for x in lector.en_linea(s):
        if isinstance(x, Texto):
            partes.append(x.texto)
        elif isinstance(x, Enlace):
            partes += [y.texto for y in x.contenido if isinstance(y, Texto)]
    return re.sub(r'\s+', ' ', ''.join(partes).replace('\u00a0', ' ')).strip()


LECTOR_GLOBAL = None


def leer_manual():
    """Bloques del manual completo, en orden, desde manual-administrador.tex."""
    global LECTOR_GLOBAL
    lector = Lector()
    LECTOR_GLOBAL = lector
    principal = sin_comentarios((MANUAL / 'manual-administrador.tex').read_text(encoding='utf-8'))
    cuerpo = principal[principal.index('\\begin{document}') + len('\\begin{document}'):principal.index('\\end{document}')]
    return lector.bloques(cuerpo), lector


if __name__ == '__main__':
    bloques, _ = leer_manual()
    from collections import Counter
    print(Counter(b.tipo for b in bloques))
    for b in bloques:
        if b.tipo in ('capitulo', 'titulo'):
            print(b.tipo, b.datos.get('nivel', ''), b.datos['numero'], b.datos['texto'])
