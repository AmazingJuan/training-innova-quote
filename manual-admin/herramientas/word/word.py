"""Genera manual-administrador.docx a partir de los fuentes LaTeX del manual.

    python3 herramientas/word/generar.py     (hace todo: figuras, Word, números de página)
"""
import re
from copy import deepcopy
from dataclasses import replace

from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.style import WD_STYLE_TYPE
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK, WD_LINE_SPACING
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor

import imagenes
import ooxml as ox
from latex import COLORES, MANUAL, Enlace, Fmt, Icono, Salto, Texto

FIGURAS = MANUAL / 'herramientas' / '.entorno' / 'word' / 'figuras'
ANCHO_TEXTO = 16.0

C = COLORES
TINTA, PIZARRA, AZUL = C['tinta'], C['pizarra'], C['azul']

FAMILIAS_TTF = {
    'Montserrat': {'Regular': 'Montserrat-Regular.ttf', 'Bold': 'Montserrat-Bold.ttf', 'Italic': 'Montserrat-Italic.ttf'},
    'Montserrat SemiBold': {'Regular': 'Montserrat-SemiBold.ttf'},
    'Montserrat ExtraBold': {'Regular': 'Montserrat-ExtraBold.ttf'},
    'Montserrat Black': {'Regular': 'Montserrat-Black.ttf'},
    'Source Sans 3': {'Regular': 'SourceSans3-Regular.ttf', 'Bold': 'SourceSans3-Bold.ttf', 'Italic': 'SourceSans3-It.ttf',
                      'BoldItalic': 'SourceSans3-BoldIt.ttf'},
    'Source Sans 3 Semibold': {'Regular': 'SourceSans3-Semibold.ttf', 'Italic': 'SourceSans3-SemiboldIt.ttf'},
}

SANGRIAS = {'itemize': (0.6, 1.45, 2.3), 'check': (0.6, 1.45, 2.3), 'pasos': (0.85, 1.7, 2.55), 'zonas': (0.85, 1.7, 2.55)}


def familia(display, peso, mono):
    """(tipografía, negrita) para la combinación de estilo."""
    if mono:
        return 'Consolas', False
    if display:
        return {'normal': ('Montserrat', False), 'sb': ('Montserrat SemiBold', False), 'b': ('Montserrat', True),
                'eb': ('Montserrat ExtraBold', False), 'ub': ('Montserrat Black', False)}[peso]
    return {'normal': ('Source Sans 3', False), 'sb': ('Source Sans 3 Semibold', False), 'b': ('Source Sans 3', True),
            'eb': ('Source Sans 3', True), 'ub': ('Source Sans 3', True)}[peso]


def combinar(ctx, fmt):
    return Fmt(peso=fmt.peso if fmt.peso != 'normal' else ctx.peso, italica=fmt.italica or ctx.italica,
               mono=fmt.mono or ctx.mono, display=fmt.display or ctx.display, color=fmt.color or ctx.color,
               tamano=fmt.tamano or ctx.tamano, caja=fmt.caja or ctx.caja)


# Formato de cada tipo de caja en línea: (sombreado, borde, color, display, peso, tamaño)
CAJAS = {
    'boton': (AZUL, None, 'FFFFFF', True, 'b', 8.5),
    'botonsec': ('FFFFFF', AZUL, AZUL, True, 'b', 8.5),
    'botonrojo': ('FFFFFF', C['error'], C['error'], True, 'b', 8.5),
    'campo': (C['nube'], C['linea'], C['noche'], False, 'sb', 10),
    'tecla': ('FFFFFF', C['grismarca'], C['noche'], True, 'sb', 9),
}


class Generador:
    def __init__(self, bloques, lector, paginas=None):
        self.bloques = bloques
        self.lector = lector
        self.paginas = paginas or {}
        self.doc = Document()
        self.estilos()
        self.listas()
        self.ultima_pasos = None
        self.primera_seccion = True
        self.numeracion_pendiente = None
        self.entradas_indice = []      # (nivel, número, texto, etiqueta)
        self.figuras = []              # (número, texto, etiqueta)
        self.preparar_indices()

    # ======================================================================
    #  Estilos
    # ======================================================================
    def estilo(self, nombre, tipo=WD_STYLE_TYPE.PARAGRAPH, base='Normal', **props):
        estilos = self.doc.styles
        try:
            s = estilos[nombre]
        except KeyError:
            s = estilos.add_style(nombre, tipo)
            if base and tipo == WD_STYLE_TYPE.PARAGRAPH:
                s.base_style = estilos[base]
        if tipo == WD_STYLE_TYPE.PARAGRAPH:
            s.quick_style = True
        self.formato_estilo(s, **props)
        return s

    def formato_estilo(self, s, fuente=None, negrita=None, tam=None, color=None, antes=None, despues=None, linea=None,
                       alineado=None, sangria=None, mantener=None, cursiva=None, esquema='nada', espaciado=None):
        if fuente:
            ox.fuente(s.element.get_or_add_rPr(), fuente)
        f = s.font
        if negrita is not None:
            f.bold = negrita
        if cursiva is not None:
            f.italic = cursiva
        if tam:
            f.size = Pt(tam)
        if color:
            f.color.rgb = RGBColor.from_string(color)
        if espaciado:
            rpr = s.element.get_or_add_rPr()
            rpr.append(ox.el('w:spacing', val=round(espaciado * 20)))
            ox.ordenar(rpr, ox.ORDEN_RPR)
        if hasattr(s, 'paragraph_format'):
            pf = s.paragraph_format
            if antes is not None:
                pf.space_before = Pt(antes)
            if despues is not None:
                pf.space_after = Pt(despues)
            if linea is not None:
                pf.line_spacing = linea
            if alineado is not None:
                pf.alignment = alineado
            if sangria is not None:
                pf.left_indent = Cm(sangria)
            if mantener is not None:
                pf.keep_with_next = mantener
            if esquema != 'nada':
                ppr = s.element.get_or_add_pPr()
                e = ppr.find(qn('w:outlineLvl'))
                if e is not None:
                    ppr.remove(e)
                if esquema is not None:
                    ppr.append(ox.el('w:outlineLvl', val=esquema))
                    ox.ordenar(ppr, ox.ORDEN_PPR)

    def estilos(self):
        doc = self.doc
        # Valores por defecto: tipografía, color e idioma (corrector en español)
        defecto = doc.styles.element.find(qn('w:docDefaults')).find(qn('w:rPrDefault')).find(qn('w:rPr'))
        ox.fuente(defecto, 'Source Sans 3')
        for e in defecto.findall(qn('w:lang')):
            defecto.remove(e)
        defecto.append(ox.el('w:lang', val='es-CO', eastAsia='es-CO', bidi='ar-SA'))
        ox.ordenar(defecto, ox.ORDEN_RPR)

        self.estilo('Normal', base=None, fuente='Source Sans 3', tam=11, color=TINTA, antes=0, despues=6, linea=1.1)
        for nombre in ('Heading 1', 'Heading 2', 'Heading 3', 'Heading 4'):
            s = doc.styles[nombre]
            rpr = s.element.get_or_add_rPr()
            for e in rpr.findall(qn('w:color')):
                rpr.remove(e)
        self.estilo('Heading 1', fuente='Montserrat ExtraBold', negrita=False, tam=24, color='FFFFFF', antes=0, despues=0,
                    linea=1.0, mantener=True, esquema=0)
        self.estilo('Heading 2', fuente='Montserrat', negrita=True, tam=16, color=AZUL, antes=20, despues=8, linea=1.05,
                    mantener=True, esquema=1)
        self.estilo('Heading 3', fuente='Montserrat SemiBold', negrita=False, tam=12.5, color=C['noche'], antes=14,
                    despues=5, linea=1.05, mantener=True, esquema=2)
        self.estilo('Heading 4', fuente='Montserrat', negrita=True, cursiva=False, tam=10.5, color=C['azulhondo'],
                    antes=10, despues=3, mantener=True, esquema=3)
        self.estilo('Título sin número', fuente='Montserrat ExtraBold', tam=30, color='FFFFFF', antes=0, despues=0,
                    linea=1.0, mantener=True)
        self.estilo('Subtítulo sin número', fuente='Montserrat', negrita=True, tam=16, color=AZUL, antes=20, despues=8,
                    mantener=True)
        self.estilo('Etiqueta de capítulo', fuente='Montserrat', negrita=True, tam=9.5, color=C['cielo'], antes=0,
                    despues=4, linea=1.0, mantener=True, espaciado=2.2)
        self.estilo('Entradilla', tam=13, color=PIZARRA, antes=0, despues=12, linea=1.45)
        self.estilo('Caption', tam=10, color=PIZARRA, antes=5, despues=14, alineado=WD_ALIGN_PARAGRAPH.CENTER, cursiva=False,
                    negrita=False)
        self.estilo('Imagen', antes=8, despues=0, alineado=WD_ALIGN_PARAGRAPH.CENTER, mantener=True, linea=1.0)
        self.estilo('Texto de tabla', tam=10, antes=0, despues=0, linea=1.05)
        self.estilo('Encabezado de tabla', fuente='Montserrat', negrita=True, tam=9, color='FFFFFF', antes=0, despues=0,
                    linea=1.05)
        self.estilo('Texto de recuadro', antes=0, despues=4, linea=1.1)
        self.estilo('List Paragraph', antes=0, despues=4, linea=1.1, sangria=0.6)
        self.estilo('Header', fuente='Montserrat SemiBold', tam=9, color=PIZARRA, antes=0, despues=0)
        self.estilo('Footer', tam=9, color=PIZARRA, antes=0, despues=0)
        for nombre in ('Header', 'Footer'):
            ppr = self.doc.styles[nombre].element.get_or_add_pPr()
            for t in ppr.findall(qn('w:tabs')):
                ppr.remove(t)
        # Índices: Word usa estos nombres al actualizar la tabla de contenido
        self.estilo('toc 1', fuente='Montserrat', negrita=True, tam=11.5, color=C['noche'], antes=10, despues=2)
        self.estilo('toc 2', tam=11, color=TINTA, antes=1, despues=1)
        self.estilo('table of figures', tam=10.5, color=TINTA, antes=1, despues=1)
        for nombre, (izq, colgante) in (('toc 1', (1.2, 1.2)), ('toc 2', (2.35, 1.15)), ('table of figures', (1.5, 1.5))):
            pf = self.doc.styles[nombre].paragraph_format
            pf.left_indent, pf.first_line_indent = Cm(izq), Cm(-colgante)
            tabs = pf.tab_stops
            tabs.add_tab_stop(Cm(izq))
            from docx.enum.text import WD_TAB_ALIGNMENT, WD_TAB_LEADER
            tabs.add_tab_stop(Cm(ANCHO_TEXTO), WD_TAB_ALIGNMENT.RIGHT,
                              WD_TAB_LEADER.SPACES if nombre == 'toc 1' else WD_TAB_LEADER.DOTS)

        # Márgenes de la primera sección (las demás los heredan)
        self.pagina(doc.sections[0])

    def pagina(self, seccion):
        seccion.page_width, seccion.page_height = Cm(21), Cm(29.7)
        seccion.top_margin, seccion.bottom_margin = Cm(3), Cm(2.7)
        seccion.left_margin, seccion.right_margin = Cm(2.5), Cm(2.5)
        seccion.header_distance, seccion.footer_distance = Cm(1.2), Cm(1.2)

    # ======================================================================
    #  Numeración (títulos y listas)
    # ======================================================================
    def listas(self):
        n = self.num = ox.Numeracion(self.doc)
        d = 'Montserrat'
        n.definir('capitulos', [
            dict(formato='decimal', texto='0%1', estilo='Heading1', sangria_cm=2.6, colgante_cm=2.6, tab=2.6,
                 fuente='Montserrat Black', color='FFFFFF', tam=40),
            dict(formato='decimal', texto='%1.%2', estilo='Heading2', sangria_cm=1.3, colgante_cm=1.3, tab=1.3,
                 fuente=d, negrita=True, color=C['azulvivo'], tam=16),
            dict(formato='decimal', texto='%1.%2.%3', estilo='Heading3', sangria_cm=1.55, colgante_cm=1.55, tab=1.55,
                 fuente='Montserrat SemiBold', color=C['azulvivo'], tam=12.5),
        ])
        n.definir('apendices', [dict(formato='upperLetter', texto='%1', sangria_cm=2.6, colgante_cm=2.6, tab=2.6,
                                     fuente='Montserrat Black', color='FFFFFF', tam=40)])
        n.definir('pasos', [dict(formato='decimal', texto='%1', sangria_cm=s, colgante_cm=0.85 if i == 0 else 0.6,
                                 fuente=d, negrita=True, color=AZUL, tam=10.5) for i, s in enumerate(SANGRIAS['pasos'])])
        n.definir('zonas', [dict(formato='upperLetter', texto='%1', sangria_cm=s, colgante_cm=0.85,
                                 fuente=d, negrita=True, color=C['noche'], tam=10.5) for s in SANGRIAS['zonas']])
        n.definir('itemize', [dict(formato='bullet', texto=t, sangria_cm=s, colgante_cm=0.45, fuente='Arial', color=col, tam=tam)
                              for t, s, col, tam in (('■', 0.6, AZUL, 7), ('▪', 1.45, C['azulvivo'], 8), ('–', 2.3, PIZARRA, 9))])
        n.definir('check', [dict(formato='bullet', texto='✓', sangria_cm=s, colgante_cm=0.5, fuente='Segoe UI Symbol',
                                 color=C['cielo'], tam=10) for s in SANGRIAS['check']])
        self.num_capitulos = n.nueva('capitulos', reiniciar=False)
        self.num_apendices = n.nueva('apendices', reiniciar=False)
        self.num_viñetas = n.nueva('itemize', reiniciar=False)
        self.num_check = n.nueva('check', reiniciar=False)
        # Los títulos usan la numeración desde su estilo
        for nivel, nombre in enumerate(('Heading 1', 'Heading 2', 'Heading 3')):
            ppr = self.doc.styles[nombre].element.get_or_add_pPr()
            numpr = ox.el('w:numPr')
            if nivel:
                numpr.append(ox.el('w:ilvl', val=nivel))
            numpr.append(ox.el('w:numId', val=self.num_capitulos))
            ppr.append(numpr)
            ox.ordenar(ppr, ox.ORDEN_PPR)

    # ======================================================================
    #  Índices
    # ======================================================================
    def preparar_indices(self):
        for b in self.bloques:
            d = b.datos
            if b.tipo == 'capitulo' and d['numero']:
                self.entradas_indice.append((1, d['numero'].zfill(2) if not d['apendice'] else d['numero'], d['texto'],
                                             d['etiqueta']))
            elif b.tipo == 'titulo' and d['nivel'] == 2 and d['numero']:
                d['etiqueta'] = d['etiqueta'] or f'tit:{d["numero"]}'
                self.entradas_indice.append((2, d['numero'], d['texto'], d['etiqueta']))
            elif b.tipo == 'titulo' and d['numero'] and not d['etiqueta']:
                d['etiqueta'] = f'tit:{d["numero"]}'
            if b.tipo in ('captura', 'figura'):
                self.figuras.append((d['numero'], d['pie_texto'], d['etiqueta']))

    def pagina_de(self, etiqueta):
        return str(self.paginas.get(etiqueta, ''))

    # ======================================================================
    #  Secciones, encabezados y pies
    # ======================================================================
    def nueva_seccion(self, titulo_encabezado=None, sin_marcas=False):
        if self.primera_seccion:
            seccion = self.doc.sections[0]
            self.primera_seccion = False
        else:
            seccion = self.doc.add_section(WD_SECTION.NEW_PAGE)
        self.pagina(seccion)
        seccion.different_first_page_header_footer = True
        sectpr = seccion._sectPr
        for e in sectpr.findall(qn('w:pgNumType')):
            sectpr.remove(e)
        if self.numeracion_pendiente:
            formato = 'lowerRoman' if self.numeracion_pendiente == 'roman' else 'decimal'
            sectpr.append(ox.el('w:pgNumType', fmt=formato, start=1))
            self.formato_paginas = formato
            self.numeracion_pendiente = None
        elif getattr(self, 'formato_paginas', None):
            sectpr.append(ox.el('w:pgNumType', fmt=self.formato_paginas))
        ox.ordenar(sectpr, ['headerReference', 'footerReference', 'footnotePr', 'endnotePr', 'type', 'pgSz', 'pgMar',
                            'paperSrc', 'pgBorders', 'lnNumType', 'pgNumType', 'cols', 'formProt', 'vAlign', 'noEndnote',
                            'titlePg', 'textDirection', 'bidi', 'rtlGutter', 'docGrid', 'printerSettings'])

        for cabecera in (seccion.header, seccion.first_page_header, seccion.footer, seccion.first_page_footer):
            cabecera.is_linked_to_previous = False
            for p in cabecera.paragraphs[1:]:
                p._p.getparent().remove(p._p)
            cabecera.paragraphs[0].clear()
        if sin_marcas:
            return seccion
        # Encabezado: logo a la izquierda, título a la derecha y una línea debajo
        p = seccion.header.paragraphs[0]
        p.style = self.doc.styles['Header']
        p.paragraph_format.tab_stops.clear_all()
        from docx.enum.text import WD_TAB_ALIGNMENT
        p.paragraph_format.tab_stops.add_tab_stop(Cm(ANCHO_TEXTO), WD_TAB_ALIGNMENT.RIGHT)
        r = p.add_run()
        r.add_picture(str(MANUAL / 'imagenes' / 'logo.png'), height=Cm(0.75))
        p.add_run('\t' + titulo_encabezado)
        p.paragraph_format.space_after = Pt(4)
        ox.bordes_parrafo(p, bottom=ox.borde('single', 6, C['linea'], 4))
        # Pie: texto a la izquierda y el número de página en un recuadro azul
        for pie in (seccion.footer, seccion.first_page_footer):
            p = pie.paragraphs[0]
            p.style = self.doc.styles['Footer']
            p.paragraph_format.tab_stops.add_tab_stop(Cm(ANCHO_TEXTO), WD_TAB_ALIGNMENT.RIGHT)
            p.add_run(f'Manual del administrador · Training InnovaQuote · Versión {self.lector.datos["versiondoc"]}\t')
            caja = p.add_run('\u00a0\u00a0')
            self.formato_corrida(caja, Fmt(display=True, peso='b', color='FFFFFF', tamano=9))
            ox.sombra_texto(caja, AZUL)
            ox.campo(p._p, 'PAGE', '1', rpr_base=caja._r.rPr)
            fin = p.add_run('\u00a0\u00a0')
            self.formato_corrida(fin, Fmt(display=True, peso='b', color='FFFFFF', tamano=9))
            ox.sombra_texto(fin, AZUL)
        return seccion

    # ======================================================================
    #  Texto en línea
    # ======================================================================
    def formato_corrida(self, r, f):
        # Solo se fija la tipografía cuando cambia respecto del estilo del párrafo
        if f.mono or f.display or f.peso in ('sb', 'eb', 'ub'):
            nombre, negrita = familia(f.display, f.peso, f.mono)
            ox.fuente(r, nombre)
            r.bold = negrita or None
        elif f.peso == 'b':
            r.bold = True
        r.italic = f.italica or None
        if f.color:
            r.font.color.rgb = RGBColor.from_string(f.color)
        if f.tamano:
            r.font.size = Pt(f.tamano)
        if f.mono:
            ox.sin_revision(r)

    def corrida_texto(self, p, texto, f):
        r = p.add_run(texto)
        caja = f.caja
        if caja:
            if caja[0] == 'insignia':
                fondo, borde_c, letra = caja[1], caja[2], caja[3]
                f = replace(f, color=letra, peso='sb', display=False, tamano=9)
                self.formato_corrida(r, f)
                ox.sombra_texto(r, fondo)
                ox.borde_texto(r, borde_c, 4)
            else:
                fondo, borde_c, letra, display, peso, tam = CAJAS[caja[0]]
                f = replace(f, color=letra, display=display, peso=peso, tamano=tam, italica=False)
                self.formato_corrida(r, f)
                ox.sombra_texto(r, fondo)
                if borde_c:
                    ox.borde_texto(r, borde_c, 6 if caja[0] != 'campo' else 4)
            ox.sin_revision(r)
        else:
            self.formato_corrida(r, f)
        return r

    def corrida_icono(self, p, codigo, f):
        tam = f.tamano or 11
        color = f.color or TINTA
        fondo = None
        if f.caja and f.caja[0] != 'insignia':
            fondo_c, _, letra, *_resto = CAJAS[f.caja[0]]
            color, fondo, tam = letra, fondo_c, CAJAS[f.caja[0]][5]
        elif f.caja:
            color, fondo, tam = f.caja[3], f.caja[1], 9
        png = imagenes.icono(codigo, color, fondo=fondo, alto=96, margen=0.12 if fondo else 0.0)
        r = p.add_run()
        r.add_picture(str(png), height=Pt(tam * (0.95 if fondo else 0.82)))
        ox.bajar_imagen(r, -tam * (0.16 if fondo else 0.06))
        if fondo:
            ox.sombra_texto(r, fondo)
        return r

    def escribir(self, p, contenido, ctx=Fmt()):
        """Añade el texto enriquecido al párrafo."""
        i = 0
        while i < len(contenido):
            x = contenido[i]
            # Las cajas (botones, campos…) llevan un espacio de relleno a cada lado
            caja = getattr(getattr(x, 'fmt', None), 'caja', None)
            if caja and (i == 0 or getattr(getattr(contenido[i - 1], 'fmt', None), 'caja', None) != caja):
                self.corrida_texto(p, '\u00a0', combinar(ctx, x.fmt))
            if isinstance(x, Texto):
                self.corrida_texto(p, x.texto, combinar(ctx, x.fmt))
            elif isinstance(x, Icono):
                self.corrida_icono(p, x.codigo, combinar(ctx, x.fmt))
            elif isinstance(x, Salto):
                p.add_run().add_break(WD_BREAK.LINE)
            elif isinstance(x, Enlace):
                h = ox.hipervinculo_interno(p._p, x.destino)
                antes = len(p._p)
                self.escribir(p, x.contenido, ctx)
                for r in list(p._p)[antes:]:
                    h.append(r)
            if caja and (i == len(contenido) - 1 or getattr(getattr(contenido[i + 1], 'fmt', None), 'caja', None) != caja):
                self.corrida_texto(p, '\u00a0', combinar(ctx, x.fmt))
            i += 1

    # ======================================================================
    #  Bloques
    # ======================================================================
    def generar(self, destino):
        for b in self.bloques:
            self.bloque(b, self.doc, Fmt())
        self.doc.core_properties.title = 'Training InnovaQuote: manual de usuario del administrador'
        self.doc.core_properties.subject = 'Guía paso a paso del panel de administración de Training InnovaQuote'
        self.doc.core_properties.author = 'Training Corporation'
        self.doc.core_properties.keywords = 'Training InnovaQuote, cotizaciones, manual, administrador'
        self.doc.core_properties.language = 'es-CO'
        # Modo de Word actual (sin «Modo de compatibilidad» al abrirlo)
        for e in self.doc.settings.element.iter(qn('w:compatSetting')):
            if e.get(qn('w:name')) == 'compatibilityMode':
                e.set(qn('w:val'), '15')
        ox.ordenar_todo(self.doc)
        self.doc.save(destino)
        familias = {nombre: {v: str(MANUAL / 'fuentes' / 'ttf' / archivo) for v, archivo in variantes.items()}
                    for nombre, variantes in FAMILIAS_TTF.items()}
        ox.incrustar_fuentes(destino, familias)

    def parrafo(self, cont, estilo='Normal'):
        return cont.add_paragraph(style=self.doc.styles[estilo])

    def bloque(self, b, cont, ctx, nivel_lista=0):
        d = b.datos
        tipo = b.tipo
        if tipo == 'portada':
            self.portada()
        elif tipo == 'contraportada':
            self.contraportada()
        elif tipo == 'numeracion':
            self.numeracion_pendiente = d['formato']
        elif tipo == 'capitulo':
            self.capitulo(d)
        elif tipo == 'tableofcontents':
            self.indice()
        elif tipo == 'listoffigures':
            self.indice_figuras()
        elif tipo == 'titulo':
            self.titulo(d, cont)
        elif tipo == 'parrafo':
            self.escribir(self.parrafo(cont, 'Texto de recuadro' if cont is not self.doc else 'Normal'), d['contenido'], ctx)
        elif tipo == 'entradilla':
            self.escribir(self.parrafo(cont, 'Entradilla'), d['contenido'], ctx)
        elif tipo == 'lista':
            self.lista(d, cont, ctx, nivel_lista)
        elif tipo == 'aviso':
            self.aviso(d, cont)
        elif tipo == 'tabla':
            self.tabla(d, cont)
        elif tipo == 'ficha':
            self.ficha(d, cont)
        elif tipo == 'captura':
            self.captura(d, cont)
        elif tipo == 'figura':
            self.figura(d, cont)
        elif tipo == 'faq':
            self.faq(d, cont)
        elif tipo == 'caja':
            self.caja(d, cont)
        elif tipo == 'columnas':
            self.columnas(d, cont)
        else:
            raise ValueError(f'bloque sin traducción a Word: {tipo}')

    # ---- portada y contraportada -------------------------------------------------
    def fondo_pagina(self, imagen, ancho=21.0, alto=29.7):
        p = self.parrafo(self.doc)
        p.paragraph_format.space_after = Pt(0)
        p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.EXACTLY
        p.paragraph_format.line_spacing = Pt(1)
        r = p.add_run()
        r.add_picture(str(imagen), width=Cm(ancho), height=Cm(alto))
        ox.anclar(r, 0, 0)
        r.font.size = Pt(1)
        return p

    def texto_en_marco(self, x, y, ancho, partes, alineado=None, interlineado=None):
        """Párrafo en un marco en (x, y) cm de la página. partes: [(texto, Fmt, espaciado_letras)]."""
        p = self.parrafo(self.doc)
        pf = p.paragraph_format
        pf.space_before = pf.space_after = Pt(0)
        if interlineado:
            pf.line_spacing_rule = WD_LINE_SPACING.EXACTLY
            pf.line_spacing = Pt(interlineado)
        else:
            pf.line_spacing = 1.0
        for texto, f, letras in partes:
            if texto == '\n':
                p.add_run().add_break(WD_BREAK.LINE)
                continue
            r = self.corrida_texto(p, texto, f)
            if letras:
                ox.espaciado_letras(r, letras)
        ox.marco(p, x, y, ancho, alineado)
        return p

    def portada(self):
        self.nueva_seccion(sin_marcas=True)
        self.fondo_pagina(FIGURAS / 'portada.png')
        blanco = 'FFFFFF'
        cielo = C['cielo']
        self.texto_en_marco(2.4, 3.5, 16, [('MANUAL DE USUARIO', Fmt(display=True, peso='b', color=cielo, tamano=10.5), 1.9)])
        self.texto_en_marco(2.4, 4.0, 17, [('Training', Fmt(display=True, peso='ub', color=blanco, tamano=52), None), ('\n', None, None),
                                           ('InnovaQuote', Fmt(display=True, peso='ub', color=blanco, tamano=52), None)],
                            interlineado=58)
        self.texto_en_marco(2.4, 8.7, 16.2, [('Administrador del panel', Fmt(display=True, peso='eb', color=blanco, tamano=24), None),
                                             ('\n', None, None),
                                             ('de cotizaciones virtuales', Fmt(display=True, peso='eb', color=blanco, tamano=24), None)],
                            interlineado=30)
        self.texto_en_marco(2.4, 11.8, 13.8, [('Guía paso a paso, con capturas de pantalla, de todo lo que puede hacer el '
                                               'usuario administrador: desde el inicio de sesión hasta el cierre de sesión.',
                                               Fmt(color=blanco, tamano=13.5), None)], interlineado=19)
        self.texto_en_marco(2.4, 14.55, 16, [(self.lector.datos['dominio'], Fmt(display=True, peso='sb', color=cielo, tamano=13), None)])
        self.texto_en_marco(2.4, 18.6, 10, [('EN ESTE MANUAL', Fmt(display=True, peso='b', color=PIZARRA, tamano=8), 1.45)])
        for (x, y), texto in zip(((2.4, 20.0), (2.4, 21.2), (2.4, 22.4), (11.0, 20.0), (11.0, 21.2), (11.0, 22.4)),
                                 ('Acceso al panel', 'Cotizaciones y clientes', 'Catálogo y árbol de decisión',
                                  'Usuarios, correos y configuración', 'Ver el sitio y cerrar sesión', 'Capacidad de la aplicación')):
            self.texto_en_marco(x + 1.0, y - 0.25, 7.2, [(texto, Fmt(display=True, peso='sb', color=C['noche'], tamano=10.5), None)])
        self.texto_en_marco(12.6, 27.85, 6.0, [(f'Versión {self.lector.datos["versiondoc"]}', Fmt(display=True, peso='b', color=blanco, tamano=10), None),
                                               ('\n', None, None), (self.lector.datos['fechadoc'], Fmt(color=C['niebla'], tamano=10), None)],
                            alineado='right')

    def contraportada(self):
        self.nueva_seccion(sin_marcas=True)
        self.fondo_pagina(FIGURAS / 'contraportada.png')
        self.texto_en_marco(0, 23.95, 21, [('Training Corporation', Fmt(display=True, peso='b', color='FFFFFF', tamano=10), None),
                                           ('\n', None, None), (self.lector.datos['dominio'], Fmt(color=C['niebla'], tamano=10), None)],
                            alineado='center', interlineado=14)
        self.texto_en_marco(0, 25.55, 21, [(f'Training InnovaQuote · Manual del administrador · Versión {self.lector.datos["versiondoc"]} · '
                                            f'{self.lector.datos["fechadoc"]}', Fmt(color=C['bruma'], tamano=9), None)],
                            alineado='center')

    # ---- capítulos y títulos ---------------------------------------------------------
    def banda(self, encabezado):
        """Primera página de un capítulo: nueva sección, banda azul y celda donde va el título."""
        self.nueva_seccion(encabezado)
        self.fondo_pagina(FIGURAS / 'banda.png', 21, 7)
        tabla = self.doc.add_table(rows=1, cols=1)
        ox.tabla_sin_bordes(tabla)
        ox.ancho_tabla(tabla, ANCHO_TEXTO)
        ox.anchos_columnas(tabla, [ANCHO_TEXTO])
        ox.alto_fila(tabla.rows[0], 3.9)
        celda = tabla.cell(0, 0)
        ox.margenes_celda(celda, 0, 0, 0, 0)
        ox.alinear_vertical(celda, 'bottom')
        ox.ordenar_tcpr(celda)
        # Separación entre la banda y el texto del capítulo
        p = self.parrafo(self.doc)
        pf = p.paragraph_format
        pf.space_before = pf.space_after = Pt(0)
        pf.line_spacing_rule = WD_LINE_SPACING.EXACTLY
        pf.line_spacing = Pt(24)
        return celda

    def capitulo(self, d):
        if d['numero'] is None:
            celda = self.banda(d['texto'])
            p = celda.paragraphs[0]
            p.style = self.doc.styles['Título sin número']
            p.paragraph_format.space_after = Pt(17)
            self.escribir(p, d['titulo'])
            if d['etiqueta']:
                ox.marcador(p, d['etiqueta'])
            return
        apendice = d['apendice']
        encabezado = f'{"Apéndice" if apendice else "Capítulo"} {d["numero"]} · {d["texto"]}'
        celda = self.banda(encabezado)
        p = celda.paragraphs[0]
        p.style = self.doc.styles['Etiqueta de capítulo']
        p.add_run('APÉNDICE' if apendice else 'CAPÍTULO')
        h = celda.add_paragraph(style=self.doc.styles['Heading 1'])
        h.paragraph_format.space_after = Pt(15)
        if apendice:
            ox.numerar(h, self.num_apendices, 0)
        self.escribir(h, d['titulo'])
        ox.marcador(h, d['etiqueta'])

    def titulo(self, d, cont):
        nivel, numero = d['nivel'], d['numero']
        if nivel == 2 and numero is None:
            p = self.parrafo(cont, 'Subtítulo sin número')
        else:
            p = self.parrafo(cont, f'Heading {nivel}')
        self.escribir(p, d['titulo'])
        if d.get('etiqueta'):
            ox.marcador(p, d['etiqueta'])

    # ---- índices -------------------------------------------------------------------
    def indice(self):
        celda = self.banda('Contenido')
        p = celda.paragraphs[0]
        p.style = self.doc.styles['Título sin número']
        p.paragraph_format.space_after = Pt(17)
        p.add_run('Contenido')
        primero = True
        ultimo = None
        for nivel, numero, texto, etiqueta in self.entradas_indice:
            e = self.parrafo(self.doc, f'toc {nivel}')
            if primero:
                ox.campo_inicio(e._p, 'TOC \\o "1-2" \\h \\z \\u')
                primero = False
            h = ox.hipervinculo_interno(e._p, etiqueta)
            antes = len(e._p)
            num_r = e.add_run(numero + '\t')
            if nivel == 1:
                self.formato_corrida(num_r, Fmt(display=True, peso='b', color=AZUL, tamano=11.5))
            e.add_run(texto + '\t')
            pag_ini = len(e._p)
            ox.campo(e._p, f'PAGEREF {ox.nombre_marcador(etiqueta)} \\h', self.pagina_de(etiqueta))
            for r in list(e._p)[antes:]:
                h.append(r)
            if nivel == 1:
                for r in h.findall(qn('w:r'))[-5:]:
                    rp = r.find(qn('w:rPr'))
                    if rp is None:
                        rp = ox.el('w:rPr')
                        r.insert(0, rp)
                    rp.append(ox.el('w:color', val=AZUL))
                    ox.ordenar(rp, ox.ORDEN_RPR)
            ultimo = e
        ox.campo_fin(ultimo._p)

    def indice_figuras(self):
        celda = self.banda('Índice de figuras')
        p = celda.paragraphs[0]
        p.style = self.doc.styles['Título sin número']
        p.paragraph_format.space_after = Pt(17)
        p.add_run('Índice de figuras')
        capitulo_previo = None
        for numero, texto, etiqueta in self.figuras:
            e = self.parrafo(self.doc, 'table of figures')
            if capitulo_previo and numero.split('.')[0] != capitulo_previo:
                e.paragraph_format.space_before = Pt(9)
            capitulo_previo = numero.split('.')[0]
            h = ox.hipervinculo_interno(e._p, etiqueta)
            antes = len(e._p)
            r = e.add_run(numero + '\t')
            self.formato_corrida(r, Fmt(display=True, peso='sb', color=AZUL, tamano=10.5))
            e.add_run(texto + '\t')
            ox.campo(e._p, f'PAGEREF {ox.nombre_marcador(etiqueta)} \\h', self.pagina_de(etiqueta))
            for r in list(e._p)[antes:]:
                h.append(r)

    # ---- listas ------------------------------------------------------------------------
    def lista(self, d, cont, ctx, nivel):
        tipo = d['tipo']
        if d['marca'] == 'check':
            tipo, num = 'check', self.num_check
        elif tipo == 'pasos':
            num = self.ultima_pasos if d['continua'] and self.ultima_pasos else self.num.nueva('pasos')
            self.ultima_pasos = num
        elif tipo == 'zonas':
            num = self.num.nueva('zonas')
        else:
            num = self.num_viñetas
        sangria = SANGRIAS[tipo][min(nivel, 2)]
        for item in d['items']:
            primero = True
            for sub in item:
                if sub.tipo == 'parrafo':
                    p = self.parrafo(cont, 'List Paragraph')
                    if primero:
                        ox.numerar(p, num, min(nivel, 2))
                    else:
                        p.paragraph_format.left_indent = Cm(sangria)
                    self.escribir(p, sub.datos['contenido'], ctx)
                elif sub.tipo == 'lista':
                    self.lista(sub.datos, cont, ctx, nivel + 1)
                else:
                    self.bloque(sub, cont, ctx, nivel + 1)
                primero = False

    # ---- recuadros -----------------------------------------------------------------
    def celda_unica(self, cont, fondo=None, borde_izq=None, borde_todo=None, margenes=(0.28, 0.39, 0.28, 0.39)):
        tabla = cont.add_table(rows=1, cols=1)
        ox.tabla_sin_bordes(tabla)
        ancho = ANCHO_TEXTO if cont is self.doc else ANCHO_TEXTO - 1.2
        ox.ancho_tabla(tabla, ancho)
        ox.anchos_columnas(tabla, [ancho])
        celda = tabla.cell(0, 0)
        if borde_izq:
            ox.bordes_celda(celda, left=ox.borde('single', 28, borde_izq, 0))
        if borde_todo:
            lado = ox.borde('single', 6, borde_todo, 0)
            ox.bordes_celda(celda, top=lado, left=lado, bottom=lado, right=lado)
        if fondo:
            ox.sombra_celda(celda, fondo)
        ox.margenes_celda(celda, *margenes)
        ox.ordenar_tcpr(celda)
        return tabla, celda

    def contenido_celda(self, celda, bloques, ctx=Fmt(), primero=None):
        """Escribe bloques en la celda reutilizando su párrafo inicial vacío."""
        inicial = celda.paragraphs[0]
        for b in bloques:
            self.bloque(b, celda, ctx)
        if not inicial.text and len(celda._tc) > 2 and not inicial._p.findall('.//' + qn('w:drawing')):
            if primero is None:
                inicial._p.getparent().remove(inicial._p)
        # Sin espacio extra al final del recuadro
        ultimo = celda.paragraphs[-1]
        ultimo.paragraph_format.space_after = Pt(0)

    def separacion(self, cont):
        """Párrafo mínimo después de un recuadro, para que dos tablas seguidas no se unan."""
        if cont is not self.doc:
            return
        p = self.parrafo(self.doc)
        pf = p.paragraph_format
        pf.space_before = pf.space_after = Pt(0)
        pf.line_spacing_rule = WD_LINE_SPACING.EXACTLY
        pf.line_spacing = Pt(8)
        p.add_run().font.size = Pt(4)

    def aviso(self, d, cont):
        self.separacion_antes(cont)
        tabla, celda = self.celda_unica(cont, fondo=d['fondo'], borde_izq=d['borde'])
        p = celda.paragraphs[0]
        p.style = self.doc.styles['Texto de recuadro']
        p.paragraph_format.keep_with_next = True
        self.corrida_icono(p, d['icono'], Fmt(color=d['borde'], tamano=11))
        r = p.add_run('\u2002' + d['titulo'])
        self.formato_corrida(r, Fmt(display=True, peso='b', color=d['borde'], tamano=10.5))
        celda.add_paragraph  # (contenido a continuación)
        self.contenido_celda(celda, d['contenido'], primero=False)
        self.separacion(cont)

    def separacion_antes(self, cont):
        if cont is self.doc:
            cuerpo = self.doc.element.body
            ultimo = cuerpo[-2] if len(cuerpo) > 1 else None   # el último es sectPr
            if ultimo is not None and ultimo.tag == qn('w:tbl'):
                self.separacion(cont)

    def faq(self, d, cont):
        self.separacion_antes(cont)
        tabla, celda = self.celda_unica(cont, fondo='FFFFFF', borde_todo=C['linea'], margenes=(0.3, 0.42, 0.3, 0.42))
        ox.fila_sin_cortes(tabla.rows[0])
        p = celda.paragraphs[0]
        p.style = self.doc.styles['Texto de recuadro']
        self.corrida_icono(p, 'F059', Fmt(color=AZUL, tamano=11))
        r = p.add_run('\u2002')
        cont_titulo = len(p._p)
        self.escribir(p, d['pregunta'], Fmt(display=True, peso='b', color=AZUL, tamano=11))
        self.contenido_celda(celda, d['respuesta'], primero=False)
        self.separacion(cont)

    def caja(self, d, cont):
        self.separacion_antes(cont)
        if d['tipo'] == 'oscuro':
            tabla, celda = self.celda_unica(cont, fondo=C['noche'], margenes=(0.5, 0.56, 0.5, 0.56))
            ctx = Fmt(color='FFFFFF')
        else:
            tabla, celda = self.celda_unica(cont, fondo=C['nube'], borde_izq=AZUL, margenes=(0.45, 0.56, 0.45, 0.56))
            ctx = Fmt()
        ox.fila_sin_cortes(tabla.rows[0])
        self.contenido_celda(celda, d['contenido'], ctx)
        self.separacion(cont)

    def ficha(self, d, cont):
        self.separacion_antes(cont)
        tabla = cont.add_table(rows=1, cols=2)
        lado = ox.borde('single', 5, C['linea'], 0)
        ox.bordes_tabla(tabla, top=lado, left=lado, bottom=lado, right=lado)
        ox.ancho_tabla(tabla, ANCHO_TEXTO)
        ox.anchos_columnas(tabla, [1.9, ANCHO_TEXTO - 1.9])
        ox.fila_sin_cortes(tabla.rows[0])
        icono_c, datos_c = tabla.cell(0, 0), tabla.cell(0, 1)
        for c in (icono_c, datos_c):
            ox.sombra_celda(c, C['nube'])
        ox.margenes_celda(icono_c, 0.3, 0.35, 0.3, 0.1)
        ox.margenes_celda(datos_c, 0.28, 0.25, 0.28, 0.35)
        ox.ordenar_tcpr(icono_c)
        ox.ordenar_tcpr(datos_c)
        p = icono_c.paragraphs[0]
        p.paragraph_format.space_after = Pt(0)
        p.add_run().add_picture(str(imagenes.cuadro_icono(d['icono'])), width=Cm(1.2))
        filas = (('UBICACIÓN', d['ubicacion'], False), ('DIRECCIÓN', [Texto(d['ruta'], Fmt(mono=True, color=C['azulhondo'], tamano=10))], True),
                 ('PERMITE', d['permite'], False))
        for i, (etiqueta, valor, mono) in enumerate(filas):
            p = datos_c.paragraphs[0] if i == 0 else datos_c.add_paragraph()
            p.style = self.doc.styles['Texto de tabla']
            pf = p.paragraph_format
            pf.left_indent, pf.first_line_indent = Cm(2.4), Cm(-2.4)
            pf.space_after = Pt(3 if i < 2 else 0)
            pf.tab_stops.add_tab_stop(Cm(2.4))
            r = p.add_run(etiqueta)
            self.formato_corrida(r, Fmt(display=True, peso='b', color=PIZARRA, tamano=7.5))
            ox.espaciado_letras(r, 0.6)
            p.add_run('\t')
            self.escribir(p, valor, Fmt(tamano=10))
        self.separacion(cont)

    # ---- tablas ------------------------------------------------------------------------
    def tabla(self, d, cont):
        self.separacion_antes(cont)
        columnas, filas = d['columnas'], d['filas']
        disponible = ANCHO_TEXTO if cont is self.doc else ANCHO_TEXTO - 1.2
        fijos = sum(c['ancho'] for c in columnas if c['ancho'])
        libres = [c for c in columnas if not c['ancho']]
        anchos = [c['ancho'] or (disponible - fijos) / len(libres) for c in columnas]
        if not libres:
            factor = disponible / fijos
            anchos = [a * factor for a in anchos]
        tabla = cont.add_table(rows=len(filas), cols=len(columnas))
        linea = ox.borde('single', 4, C['linea'], 0)
        ox.bordes_tabla(tabla, top=linea, bottom=linea, insideH=linea)
        ox.ancho_tabla(tabla, disponible)
        ox.anchos_columnas(tabla, anchos)
        ox.margenes_celdas(tabla, 0.13, 0.22, 0.13, 0.22)
        tabla.alignment = WD_TABLE_ALIGNMENT.LEFT
        for i, fila in enumerate(filas):
            columna = 0
            for celda_d in fila:
                celda = tabla.cell(i, columna)
                if celda_d['union'] > 1:
                    celda = celda.merge(tabla.cell(i, columna + celda_d['union'] - 1))
                p = celda.paragraphs[0]
                if i == 0:
                    p.style = self.doc.styles['Encabezado de tabla']
                    ox.sombra_celda(celda, AZUL)
                    ctx = Fmt(display=True, peso='b', color='FFFFFF', tamano=9)
                else:
                    p.style = self.doc.styles['Texto de tabla']
                    fondo = celda_d['fondo'] or (C['nube'] if i % 2 == 1 else None)
                    if fondo:
                        ox.sombra_celda(celda, fondo)
                    ctx = Fmt(tamano=10)
                info = columnas[columna]
                if info['alineado'] == 'centro':
                    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
                if info['medio'] or i == 0:
                    ox.alinear_vertical(celda, 'center')
                ox.ordenar_tcpr(celda)
                self.escribir(p, celda_d['contenido'], ctx)
                columna += celda_d['union']
        ox.fila_encabezado(tabla.rows[0])
        for fila in tabla.rows:
            ox.fila_sin_cortes(fila)
        self.separacion(cont)

    def columnas(self, d, cont):
        tabla = cont.add_table(rows=len(d['filas']), cols=3)
        ox.tabla_sin_bordes(tabla)
        ox.ancho_tabla(tabla, ANCHO_TEXTO - 2, 1.0)
        ox.anchos_columnas(tabla, [(ANCHO_TEXTO - 2) / 3] * 3)
        for i, fila in enumerate(d['filas']):
            for j, contenido in enumerate(fila):
                p = tabla.cell(i, j).paragraphs[0]
                p.style = self.doc.styles['Texto de tabla']
                self.escribir(p, contenido, Fmt(tamano=d['tamano']))
        self.separacion(cont)

    # ---- capturas y figuras -----------------------------------------------------------------
    def pie(self, cont, d):
        p = self.parrafo(cont, 'Caption')
        r = p.add_run(f'Figura {d["numero"]}.')
        self.formato_corrida(r, Fmt(display=True, peso='b', color=AZUL, tamano=10))
        p.add_run('\u2002')
        self.escribir(p, d['pie'], Fmt(tamano=10, color=PIZARRA))
        ox.marcador(p, d['etiqueta'])
        return p

    def imagen(self, cont, png, ancho_cm):
        p = self.parrafo(cont, 'Imagen')
        p.add_run().add_picture(str(png), width=Cm(ancho_cm))
        return p

    def captura(self, d, cont):
        dominio = self.lector.datos['dominio']
        archivo = d['archivo']
        if d['tipo'] == 'captura':
            png = imagenes.navegador(archivo, dominio + d['ruta'])
            self.imagen(cont, png, imagenes.ancho_cm(png))
        elif d['tipo'] == 'capturaventana':
            png = imagenes.ventana_excel(archivo, d['ruta'].replace('\\_', '_'))
            self.imagen(cont, png, imagenes.ancho_cm(png))
        elif d['tipo'] == 'capturamovil':
            png = imagenes.telefono(archivo)
            self.imagen(cont, png, 6.4)
        else:  # captura con notas a la derecha
            png = imagenes.navegador(archivo, dominio + d['ruta'])
            tabla = cont.add_table(rows=1, cols=2)
            ox.tabla_sin_bordes(tabla)
            ox.ancho_tabla(tabla, ANCHO_TEXTO)
            ox.anchos_columnas(tabla, [6.9, ANCHO_TEXTO - 6.9])
            ox.fila_sin_cortes(tabla.rows[0])
            izq, der = tabla.cell(0, 0), tabla.cell(0, 1)
            ox.margenes_celda(izq, 0, 0, 0, 0.3)
            ox.margenes_celda(der, 0, 0.3, 0, 0)
            ox.ordenar_tcpr(izq)
            ox.ordenar_tcpr(der)
            p = izq.paragraphs[0]
            p.style = self.doc.styles['Imagen']
            p.paragraph_format.space_before = Pt(0)
            p.add_run().add_picture(str(png), width=Cm(min(6.6, imagenes.ancho_cm(png, 6.6, 12.5))))
            self.contenido_celda(der, d['notas'], Fmt(tamano=10))
            tabla_xml = tabla._tbl
            # El pie va después de la tabla
        self.pie(cont, d)

    def figura(self, d, cont):
        png = FIGURAS / f'{d["archivo"]}.png'
        self.imagen(cont, png, imagenes.ancho_figura_cm(png))
        self.pie(cont, d)
