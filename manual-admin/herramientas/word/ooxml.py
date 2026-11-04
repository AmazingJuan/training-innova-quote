"""Piezas de WordprocessingML que python-docx no ofrece: campos, marcadores, hipervínculos
internos, imágenes flotantes, marcos, bordes, sombreados, numeración y tipografías incrustadas."""
import re
import uuid
import zipfile
from copy import deepcopy
from itertools import count

from docx.oxml import OxmlElement, parse_xml
from docx.oxml.ns import nsdecls, qn
from docx.shared import Cm, Emu

TWIP_POR_CM = 567
_ids = count(1000)


def twips(cm):
    return str(round(cm * TWIP_POR_CM))


def el(etiqueta, **atributos):
    """Elemento w:… con atributos w:… (los nombres con _ se escriben con :)."""
    e = OxmlElement(etiqueta)
    for k, v in atributos.items():
        e.set(qn(k.replace('__', ':')) if '__' in k else qn(f'w:{k}'), str(v))
    return e


def hijo(padre, etiqueta, **atributos):
    """Devuelve el hijo etiqueta de padre (lo crea si falta) con los atributos dados."""
    existente = padre.find(qn(etiqueta))
    if existente is None:
        existente = el(etiqueta)
        padre.append(existente)
    for k, v in atributos.items():
        existente.set(qn(f'w:{k}'), str(v))
    return existente


# Orden de los hijos de w:pPr y w:rPr según el esquema (Word es estricto con el orden).
ORDEN_PPR = ['pStyle', 'keepNext', 'keepLines', 'pageBreakBefore', 'framePr', 'widowControl', 'numPr', 'suppressLineNumbers',
             'pBdr', 'shd', 'tabs', 'suppressAutoHyphens', 'kinsoku', 'wordWrap', 'overflowPunct', 'topLinePunct',
             'autoSpaceDE', 'autoSpaceDN', 'bidi', 'adjustRightInd', 'snapToGrid', 'spacing', 'ind', 'contextualSpacing',
             'mirrorIndents', 'suppressOverlap', 'jc', 'textDirection', 'textAlignment', 'textboxTightWrap',
             'outlineLvl', 'divId', 'cnfStyle', 'rPr', 'sectPr', 'pPrChange']
ORDEN_RPR = ['rStyle', 'rFonts', 'b', 'bCs', 'i', 'iCs', 'caps', 'smallCaps', 'strike', 'dstrike', 'outline', 'shadow',
             'emboss', 'imprint', 'noProof', 'snapToGrid', 'vanish', 'webHidden', 'color', 'spacing', 'w', 'kern',
             'position', 'sz', 'szCs', 'highlight', 'u', 'effect', 'bdr', 'shd', 'fitText', 'vertAlign', 'rtl', 'cs',
             'em', 'lang', 'eastAsianLayout', 'specVanish', 'oMath']


def ordenar(contenedor, orden):
    hijos = list(contenedor)
    clave = {f'{{{contenedor.nsmap.get("w", "http://schemas.openxmlformats.org/wordprocessingml/2006/main")}}}{n}': i
             for i, n in enumerate(orden)}
    hijos.sort(key=lambda h: clave.get(h.tag, len(orden)))
    for h in hijos:
        contenedor.remove(h)
        contenedor.append(h)


def ppr(p):
    return p._p.get_or_add_pPr()


def rpr(r):
    return r._r.get_or_add_rPr()


# ---------------------------------------------------------------------------
#  Formato de párrafo y de texto
# ---------------------------------------------------------------------------
def borde(tipo='single', tam=4, color='D5E2EB', espacio=0):
    """Atributos de un borde (tam en octavos de punto)."""
    return {'val': tipo, 'sz': tam, 'space': espacio, 'color': color}


def bordes_parrafo(p, **lados):
    pbdr = hijo(ppr(p), 'w:pBdr')
    for lado in ('top', 'left', 'bottom', 'right', 'between'):
        if lado in lados:
            pbdr.append(el(f'w:{lado}', **lados[lado]))
    ordenar(ppr(p), ORDEN_PPR)


def sombreado(contenedor, color):
    e = el('w:shd', val='clear', color='auto', fill=color)
    contenedor.append(e)
    return e


def sombra_texto(r, color):
    sombreado(rpr(r), color)
    ordenar(rpr(r), ORDEN_RPR)


def borde_texto(r, color, tam=6):
    rpr(r).append(el('w:bdr', **borde('single', tam, color, 0)))
    ordenar(rpr(r), ORDEN_RPR)


def fuente(r_o_rpr, nombre):
    """Fija la tipografía en los cuatro alfabetos (sin temas)."""
    contenedor = rpr(r_o_rpr) if hasattr(r_o_rpr, '_r') else r_o_rpr
    f = contenedor.find(qn('w:rFonts'))
    if f is None:
        f = el('w:rFonts')
        contenedor.append(f)
    for atributo in list(f.attrib):
        del f.attrib[atributo]
    for k in ('ascii', 'hAnsi', 'cs', 'eastAsia'):
        f.set(qn(f'w:{k}'), nombre)
    ordenar(contenedor, ORDEN_RPR)


def espaciado_letras(r, puntos):
    rpr(r).append(el('w:spacing', val=round(puntos * 20)))
    ordenar(rpr(r), ORDEN_RPR)


def sin_revision(r):
    """Que el corrector ortográfico no marque direcciones ni nombres de archivo."""
    rpr(r).append(el('w:noProof'))
    ordenar(rpr(r), ORDEN_RPR)


def tabulaciones(p, *paradas):
    """paradas: (cm, 'left'|'right'|'center', 'none'|'dot')."""
    tabs = hijo(ppr(p), 'w:tabs')
    for cm, alineado, relleno in paradas:
        tabs.append(el('w:tab', val=alineado, leader=relleno, pos=twips(cm)))
    ordenar(ppr(p), ORDEN_PPR)


def numerar(p, num_id, nivel=0):
    numpr = hijo(ppr(p), 'w:numPr')
    numpr.append(el('w:ilvl', val=nivel))
    numpr.append(el('w:numId', val=num_id))
    ordenar(ppr(p), ORDEN_PPR)


def nivel_esquema(p, nivel):
    hijo(ppr(p), 'w:outlineLvl', val=nivel)
    ordenar(ppr(p), ORDEN_PPR)


def marco(p, x_cm, y_cm, ancho_cm, alineado=None):
    """Coloca el párrafo en un marco en una posición fija de la página (portada y contraportada)."""
    hijo(ppr(p), 'w:framePr', w=twips(ancho_cm), hSpace=0, vSpace=0, wrap='around', vAnchor='page', hAnchor='page',
         x=twips(x_cm), y=twips(y_cm))
    if alineado:
        hijo(ppr(p), 'w:jc', val=alineado)
    ordenar(ppr(p), ORDEN_PPR)


# ---------------------------------------------------------------------------
#  Campos, marcadores e hipervínculos
# ---------------------------------------------------------------------------
def _run(texto=None, rpr_base=None):
    r = el('w:r')
    if rpr_base is not None:
        r.append(deepcopy(rpr_base))
    if texto is not None:
        t = el('w:t')
        t.text = texto
        t.set('{http://www.w3.org/XML/1998/namespace}space', 'preserve')
        r.append(t)
    return r


def campo(p, instruccion, resultado='', rpr_base=None, sucio=False):
    """Campo complejo (PAGE, PAGEREF, TOC…) con su resultado ya calculado."""
    elementos = []
    inicio = _run(rpr_base=rpr_base)
    fc = el('w:fldChar', fldCharType='begin')
    if sucio:
        fc.set(qn('w:dirty'), 'true')
    inicio.append(fc)
    elementos.append(inicio)
    instr = _run(rpr_base=rpr_base)
    t = el('w:instrText')
    t.text = f' {instruccion} '
    t.set('{http://www.w3.org/XML/1998/namespace}space', 'preserve')
    instr.append(t)
    elementos.append(instr)
    sep = _run(rpr_base=rpr_base)
    sep.append(el('w:fldChar', fldCharType='separate'))
    elementos.append(sep)
    elementos.append(_run(resultado, rpr_base))
    fin = _run(rpr_base=rpr_base)
    fin.append(el('w:fldChar', fldCharType='end'))
    elementos.append(fin)
    for e in elementos:
        p.append(e)
    return elementos


def campo_inicio(contenedor, instruccion):
    """Abre un campo que envuelve varios párrafos (TOC): begin + instr + separate."""
    for tipo, texto in (('begin', None), ('instr', instruccion), ('separate', None)):
        r = el('w:r')
        if tipo == 'instr':
            t = el('w:instrText')
            t.text = f' {texto} '
            t.set('{http://www.w3.org/XML/1998/namespace}space', 'preserve')
            r.append(t)
        else:
            r.append(el('w:fldChar', fldCharType=tipo))
        contenedor.append(r)


def campo_fin(contenedor):
    r = el('w:r')
    r.append(el('w:fldChar', fldCharType='end'))
    contenedor.append(r)


def nombre_marcador(etiqueta):
    """Nombre de marcador válido en Word (oculto: empieza por _, máximo 40 caracteres)."""
    nombre = '_' + re.sub(r'[^A-Za-z0-9]', '_', etiqueta)
    return nombre[:40]


def marcador(p, etiqueta):
    """Marcador alrededor de todo el contenido del párrafo."""
    ident = str(next(_ids))
    inicio = el('w:bookmarkStart', id=ident, name=nombre_marcador(etiqueta))
    fin = el('w:bookmarkEnd', id=ident)
    p = getattr(p, '_p', p)
    pp = p.find(qn('w:pPr'))
    if pp is not None:
        pp.addnext(inicio)
    else:
        p.insert(0, inicio)
    p.append(fin)


def hipervinculo_interno(p, etiqueta):
    """w:hyperlink vacío hacia un marcador; se le añaden las corridas de texto."""
    h = el('w:hyperlink', anchor=nombre_marcador(etiqueta), history=1)
    getattr(p, '_p', p).append(h)
    return h


# ---------------------------------------------------------------------------
#  Imágenes flotantes
# ---------------------------------------------------------------------------
def anclar(run, x_cm, y_cm, detras=True, relativo='page'):
    """Convierte la imagen en línea de la corrida en una imagen flotante en (x, y) de la página."""
    inline = run._r.find('.//' + qn('wp:inline'))
    extent = inline.find(qn('wp:extent'))
    docpr = inline.find(qn('wp:docPr'))
    grafico = inline.find(qn('a:graphic'))
    marco_grafico = inline.find(qn('wp:cNvGraphicFramePr'))
    ancla = parse_xml(
        f'<wp:anchor {nsdecls("wp", "a", "pic", "r")} distT="0" distB="0" distL="0" distR="0" simplePos="0" '
        f'relativeHeight="{next(_ids)}" behindDoc="{1 if detras else 0}" locked="1" layoutInCell="0" allowOverlap="1">'
        f'<wp:simplePos x="0" y="0"/>'
        f'<wp:positionH relativeFrom="{relativo}"><wp:posOffset>{int(Cm(x_cm))}</wp:posOffset></wp:positionH>'
        f'<wp:positionV relativeFrom="{relativo}"><wp:posOffset>{int(Cm(y_cm))}</wp:posOffset></wp:positionV>'
        f'<wp:extent cx="{extent.get("cx")}" cy="{extent.get("cy")}"/>'
        f'<wp:effectExtent l="0" t="0" r="0" b="0"/><wp:wrapNone/></wp:anchor>'
    )
    ancla.append(docpr)
    ancla.append(marco_grafico if marco_grafico is not None else parse_xml(f'<wp:cNvGraphicFramePr {nsdecls("wp")}/>'))
    ancla.append(grafico)
    inline.getparent().replace(inline, ancla)
    return ancla


def bajar_imagen(run, puntos):
    """Baja (valores negativos) o sube la imagen en línea respecto de la línea base."""
    rpr(run).append(el('w:position', val=round(puntos * 2)))
    ordenar(rpr(run), ORDEN_RPR)


# ---------------------------------------------------------------------------
#  Tablas
# ---------------------------------------------------------------------------
def tabla_sin_bordes(tabla):
    tblpr = tabla._tbl.tblPr
    bordes = el('w:tblBorders')
    for lado in ('top', 'left', 'bottom', 'right', 'insideH', 'insideV'):
        bordes.append(el(f'w:{lado}', val='nil'))
    tblpr.append(bordes)


def bordes_tabla(tabla, **lados):
    tblpr = tabla._tbl.tblPr
    existentes = tblpr.find(qn('w:tblBorders'))
    if existentes is not None:
        tblpr.remove(existentes)
    bordes = el('w:tblBorders')
    for lado in ('top', 'left', 'bottom', 'right', 'insideH', 'insideV'):
        bordes.append(el(f'w:{lado}', **lados.get(lado, {'val': 'nil'})))
    tblpr.append(bordes)


def bordes_celda(celda, **lados):
    tcpr = celda._tc.get_or_add_tcPr()
    bordes = el('w:tcBorders')
    for lado in ('top', 'left', 'bottom', 'right'):
        if lado in lados:
            bordes.append(el(f'w:{lado}', **lados[lado]))
    tcpr.append(bordes)


def sombra_celda(celda, color):
    sombreado(celda._tc.get_or_add_tcPr(), color)


def margenes_celdas(tabla, arriba=0.1, izquierda=0.19, abajo=0.1, derecha=0.19):
    tblpr = tabla._tbl.tblPr
    m = el('w:tblCellMar')
    for lado, cm in (('top', arriba), ('left', izquierda), ('bottom', abajo), ('right', derecha)):
        m.append(el(f'w:{lado}', w=twips(cm), type='dxa'))
    tblpr.append(m)


def margenes_celda(celda, arriba=0.1, izquierda=0.19, abajo=0.1, derecha=0.19):
    tcpr = celda._tc.get_or_add_tcPr()
    m = el('w:tcMar')
    for lado, cm in (('top', arriba), ('left', izquierda), ('bottom', abajo), ('right', derecha)):
        m.append(el(f'w:{lado}', w=twips(cm), type='dxa'))
    tcpr.append(m)


def ancho_tabla(tabla, cm, sangria_cm=0.0):
    tblpr = tabla._tbl.tblPr
    for nombre in ('w:tblW', 'w:tblInd', 'w:tblLayout'):
        e = tblpr.find(qn(nombre))
        if e is not None:
            tblpr.remove(e)
    tblpr.append(el('w:tblW', w=twips(cm), type='dxa'))
    tblpr.append(el('w:tblInd', w=twips(sangria_cm), type='dxa'))
    tblpr.append(el('w:tblLayout', type='fixed'))
    ordenar_tblpr(tblpr)


def ordenar_tblpr(tblpr):
    orden = ['tblStyle', 'tblpPr', 'tblOverlap', 'bidiVisual', 'tblStyleRowBandSize', 'tblStyleColBandSize', 'tblW', 'jc',
             'tblCellSpacing', 'tblInd', 'tblBorders', 'shd', 'tblLayout', 'tblCellMar', 'tblLook']
    ordenar(tblpr, orden)


def anchos_columnas(tabla, anchos_cm):
    grid = tabla._tbl.tblGrid
    for i, col in enumerate(grid.findall(qn('w:gridCol'))):
        col.set(qn('w:w'), twips(anchos_cm[i]))
    for fila in tabla.rows:
        for i, celda in enumerate(fila.cells):
            celda.width = Cm(anchos_cm[i]) if i < len(anchos_cm) else celda.width


def fila_encabezado(fila):
    trpr = fila._tr.get_or_add_trPr()
    trpr.append(el('w:tblHeader'))


def fila_sin_cortes(fila):
    trpr = fila._tr.get_or_add_trPr()
    trpr.append(el('w:cantSplit'))


def alto_fila(fila, cm, regla='exact'):
    trpr = fila._tr.get_or_add_trPr()
    trpr.append(el('w:trHeight', val=twips(cm), hRule=regla))


def alinear_vertical(celda, valor='center'):
    tcpr = celda._tc.get_or_add_tcPr()
    tcpr.append(el('w:vAlign', val=valor))


def ordenar_tcpr(celda):
    orden = ['cnfStyle', 'tcW', 'gridSpan', 'hMerge', 'vMerge', 'tcBorders', 'shd', 'noWrap', 'tcMar', 'textDirection',
             'tcFitText', 'vAlign', 'hideMark']
    ordenar(celda._tc.get_or_add_tcPr(), orden)


# ---------------------------------------------------------------------------
#  Numeración
# ---------------------------------------------------------------------------
class Numeracion:
    """Define listas en numbering.xml y entrega numId para cada lista nueva."""

    def __init__(self, documento):
        self.raiz = documento.part.numbering_part.element
        # Se descartan las definiciones de la plantilla: el manual define las suyas.
        for e in list(self.raiz):
            if e.tag in (qn('w:abstractNum'), qn('w:num')):
                self.raiz.remove(e)
        self.abstractos = {}
        self.siguiente_abs = 1
        self.siguiente_num = 1

    def definir(self, nombre, niveles):
        """niveles: lista de dict(formato, texto, sangria_cm, colgante_cm, fuente, color, tam, negrita, sufijo, inicio)."""
        ident = self.siguiente_abs
        self.siguiente_abs += 1
        abstracto = el('w:abstractNum', abstractNumId=ident)
        abstracto.append(el('w:multiLevelType', val='multilevel' if len(niveles) > 1 else 'singleLevel'))
        for i, n in enumerate(niveles):
            lvl = el('w:lvl', ilvl=i)
            lvl.append(el('w:start', val=n.get('inicio', 1)))
            lvl.append(el('w:numFmt', val=n['formato']))
            if n.get('reinicio') is not None:
                lvl.append(el('w:lvlRestart', val=n['reinicio']))
            if n.get('estilo'):
                lvl.append(el('w:pStyle', val=n['estilo']))
            lvl.append(el('w:suff', val=n.get('sufijo', 'tab')))
            lvl.append(el('w:lvlText', val=n['texto']))
            lvl.append(el('w:lvlJc', val='left'))
            p = el('w:pPr')
            if n.get('tab') is not None:
                tabs = el('w:tabs')
                tabs.append(el('w:tab', val='num', pos=twips(n['tab'])))
                p.append(tabs)
            if n.get('sangria_cm') is not None:
                p.append(el('w:ind', left=twips(n['sangria_cm']), hanging=twips(n.get('colgante_cm', 0))))
            lvl.append(p)
            r = el('w:rPr')
            if n.get('fuente'):
                r.append(el('w:rFonts', ascii=n['fuente'], hAnsi=n['fuente'], cs=n['fuente'], hint='default'))
            if n.get('negrita'):
                r.append(el('w:b'))
            if n.get('color'):
                r.append(el('w:color', val=n['color']))
            if n.get('tam'):
                r.append(el('w:sz', val=round(n['tam'] * 2)))
                r.append(el('w:szCs', val=round(n['tam'] * 2)))
            lvl.append(r)
            abstracto.append(lvl)
        # Los abstractNum van antes que todos los num
        primero_num = self.raiz.find(qn('w:num'))
        if primero_num is not None:
            primero_num.addprevious(abstracto)
        else:
            self.raiz.append(abstracto)
        self.abstractos[nombre] = ident
        return ident

    def nueva(self, nombre, reiniciar=True):
        ident = self.siguiente_num
        self.siguiente_num += 1
        num = el('w:num', numId=ident)
        num.append(el('w:abstractNumId', val=self.abstractos[nombre]))
        if reiniciar:
            o = el('w:lvlOverride', ilvl=0)
            o.append(el('w:startOverride', val=1))
            num.append(o)
        self.raiz.append(num)
        return ident


# ---------------------------------------------------------------------------
#  Tipografías incrustadas
# ---------------------------------------------------------------------------
_POS = (35, 33, 31, 29, 27, 25, 22, 20, 17, 15, 12, 10, 7, 5, 3, 1)


def ofuscar(datos, guid):
    """Ofuscación de fuentes incrustadas de OOXML (ECMA-376, parte 1, §17.8.1)."""
    clave = bytes(int(guid[p:p + 2], 16) for p in _POS)
    datos = bytearray(datos)
    for i in range(32):
        datos[i] ^= clave[i % 16]
    return bytes(datos)


def incrustar_fuentes(ruta_docx, familias):
    """familias: {nombre: {'Regular'|'Bold'|'Italic'|'BoldItalic': ruta_ttf}}. Reescribe el .docx."""
    with zipfile.ZipFile(ruta_docx) as z:
        archivos = {n: z.read(n) for n in z.namelist()}

    tabla = archivos['word/fontTable.xml'].decode('utf-8')
    rels = ['<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">']
    entradas = []
    n = 0
    for familia, variantes in familias.items():
        partes = ['<w:charset w:val="00"/>', '<w:family w:val="swiss"/>', '<w:pitch w:val="variable"/>']
        for variante in ('Regular', 'Bold', 'Italic', 'BoldItalic'):
            if variante not in variantes:
                continue
            n += 1
            guid = '{' + str(uuid.uuid4()).upper() + '}'
            archivos[f'word/fonts/font{n}.odttf'] = ofuscar(open(variantes[variante], 'rb').read(), guid)
            rels.append(f'<Relationship Id="rIdF{n}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/font" '
                        f'Target="fonts/font{n}.odttf"/>')
            partes.append(f'<w:embed{variante} r:id="rIdF{n}" w:fontKey="{guid}"/>')
        entradas.append(f'<w:font w:name="{familia}">' + ''.join(partes) + '</w:font>')
        # Si la plantilla ya traía la familia, se reemplaza
        tabla = re.sub(rf'<w:font w:name="{re.escape(familia)}">.*?</w:font>', '', tabla, flags=re.S)
    rels.append('</Relationships>')
    tabla = tabla.replace('</w:fonts>', ''.join(entradas) + '</w:fonts>')
    if 'xmlns:r=' not in tabla.split('>', 2)[1]:
        tabla = tabla.replace('<w:fonts ', '<w:fonts xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" ', 1)
    archivos['word/fontTable.xml'] = tabla.encode('utf-8')
    archivos['word/_rels/fontTable.xml.rels'] = '\n'.join(rels).encode('utf-8')

    tipos = archivos['[Content_Types].xml'].decode('utf-8')
    if 'Extension="odttf"' not in tipos:
        tipos = tipos.replace('<Default ', '<Default Extension="odttf" ContentType="application/vnd.openxmlformats-officedocument.obfuscatedFont"/><Default ', 1)
    archivos['[Content_Types].xml'] = tipos.encode('utf-8')

    archivos['word/settings.xml'] = _con_fuentes_incrustadas(archivos['word/settings.xml'])

    with zipfile.ZipFile(ruta_docx, 'w', zipfile.ZIP_DEFLATED) as z:
        # [Content_Types].xml primero, como lo escribe Word
        for nombre in ['[Content_Types].xml'] + [k for k in archivos if k != '[Content_Types].xml']:
            z.writestr(nombre, archivos[nombre])


# Elementos de w:settings que deben ir antes de w:embedTrueTypeFonts (ECMA-376, CT_Settings)
_ANTES_DE_FUENTES = ('writeProtection', 'view', 'zoom', 'removePersonalInformation', 'removeDateAndTime',
                     'doNotDisplayPageBoundaries', 'displayBackgroundShape', 'printPostScriptOverText',
                     'printFractionalCharacterWidth', 'printFormsData')


def _con_fuentes_incrustadas(xml):
    from lxml import etree
    raiz = etree.fromstring(xml)
    w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
    if raiz.find(f'{{{w}}}embedTrueTypeFonts') is None:
        nuevo = etree.Element(f'{{{w}}}embedTrueTypeFonts')
        previos = [h for h in raiz if etree.QName(h).localname in _ANTES_DE_FUENTES]
        if previos:
            previos[-1].addnext(nuevo)
        else:
            raiz.insert(0, nuevo)
    return etree.tostring(raiz, xml_declaration=True, encoding='UTF-8', standalone=True)


ORDEN_TRPR = ['cnfStyle', 'divId', 'gridBefore', 'gridAfter', 'wBefore', 'wAfter', 'cantSplit', 'trHeight', 'tblHeader',
              'tblCellSpacing', 'jc', 'hidden']
ORDEN_TCPR = ['cnfStyle', 'tcW', 'gridSpan', 'hMerge', 'vMerge', 'tcBorders', 'shd', 'noWrap', 'tcMar', 'textDirection',
              'tcFitText', 'vAlign', 'hideMark']
ORDEN_TBLPR = ['tblStyle', 'tblpPr', 'tblOverlap', 'bidiVisual', 'tblStyleRowBandSize', 'tblStyleColBandSize', 'tblW', 'jc',
               'tblCellSpacing', 'tblInd', 'tblBorders', 'shd', 'tblLayout', 'tblCellMar', 'tblLook']
ORDEN_SECTPR = ['headerReference', 'footerReference', 'footnotePr', 'endnotePr', 'type', 'pgSz', 'pgMar', 'paperSrc',
                'pgBorders', 'lnNumType', 'pgNumType', 'cols', 'formProt', 'vAlign', 'noEndnote', 'titlePg',
                'textDirection', 'bidi', 'rtlGutter', 'docGrid', 'printerSettings']


def ordenar_todo(documento):
    """Deja cada contenedor de propiedades en el orden del esquema y el w:rPr de cada corrida al principio.

    LibreOffice acepta el orden que sea; Word, en cambio, puede declarar el archivo dañado."""
    raices = [documento.element, documento.styles.element]
    for seccion in documento.sections:
        for parte in (seccion.header, seccion.first_page_header, seccion.footer, seccion.first_page_footer):
            raices.append(parte._element)
    ordenes = {'pPr': ORDEN_PPR, 'rPr': ORDEN_RPR, 'trPr': ORDEN_TRPR, 'tcPr': ORDEN_TCPR, 'tblPr': ORDEN_TBLPR,
               'sectPr': ORDEN_SECTPR}
    w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
    for raiz in raices:
        for e in raiz.iter():
            if not isinstance(e.tag, str) or not e.tag.startswith(f'{{{w}}}'):
                continue
            local = e.tag.split('}')[1]
            if local in ordenes:
                ordenar(e, ordenes[local])
            if local in ('r', 'p', 'tbl', 'tr', 'tc'):
                prop = {'r': 'rPr', 'p': 'pPr', 'tbl': 'tblPr', 'tr': 'trPr', 'tc': 'tcPr'}[local]
                hijo_prop = e.find(f'{{{w}}}{prop}')
                if hijo_prop is not None and e.index(hijo_prop) != 0:
                    e.remove(hijo_prop)
                    e.insert(0, hijo_prop)
