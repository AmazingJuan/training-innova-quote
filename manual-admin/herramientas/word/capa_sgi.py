"""Aplica la capa SGI (encabezados con logo y marca de agua, estilos Encabezado y control de cambios final)
de la versión SGI aprobada al Word que genera manual-admin/herramientas/word/generar.py.

    python3 capa_sgi.py <versión SGI aprobada.docx> <manual-administrador.docx> <salida.docx>
"""
import re, shutil, sys, zipfile, os
from lxml import etree
SGI, NUEVO, SALIDA = sys.argv[1:4]
W='http://schemas.openxmlformats.org/wordprocessingml/2006/main'; w=lambda t:'{%s}%s'%(W,t)
tmp=SALIDA+'.dir'; shutil.rmtree(tmp, ignore_errors=True)
zipfile.ZipFile(NUEVO).extractall(tmp); sgi=SALIDA+'.sgi'; shutil.rmtree(sgi, ignore_errors=True); zipfile.ZipFile(SGI).extractall(sgi)
for n in range(1,31):
    shutil.copy(f'{sgi}/word/header{n}.xml', f'{tmp}/word/header{n}.xml')
    r=f'{sgi}/word/_rels/header{n}.xml.rels'
    if os.path.exists(r): shutil.copy(r, f'{tmp}/word/_rels/header{n}.xml.rels')
    elif os.path.exists(f'{tmp}/word/_rels/header{n}.xml.rels'): os.remove(f'{tmp}/word/_rels/header{n}.xml.rels')
shutil.copy(f'{sgi}/word/media/logo_sgi.png', f'{tmp}/word/media/logo_sgi.png')
# medios que ya nadie referencia (el logo de los encabezados originales)
refs=''.join(open(os.path.join(dp,f)).read() for dp,_,fs in os.walk(f'{tmp}/word/_rels') for f in fs)
for m in os.listdir(f'{tmp}/word/media'):
    if f'media/{m}' not in refs: os.remove(f'{tmp}/word/media/{m}')
sg=open(f'{sgi}/word/styles.xml').read(); st=open(f'{tmp}/word/styles.xml').read()
add=''.join(re.search(r'<w:style [^>]*w:styleId="%s".*?</w:style>'%i,sg,re.S).group(0) for i in ('Encabezado','EncabezadoCar'))
st=st.replace('</w:styles>',re.sub(r'<w:rsid w:val="[^"]*"/>','',add)+'</w:styles>'); open(f'{tmp}/word/styles.xml','w').write(st)
def limpiar(e):
    for x in e.iter():
        for a in list(x.attrib):
            if a.startswith('{http://schemas.microsoft.com/office/word/2010/wordml}') or a.startswith(w('rsid')): del x.attrib[a]
    return e
body_sgi=etree.parse(f'{sgi}/word/document.xml').getroot().find(w('body'))
k=[i for i,e in enumerate(body_sgi) if 'Control de cambios' in ''.join(e.itertext())][-1]
bloque=[limpiar(e) for e in list(body_sgi)[k-1:k+5]]
# encabezados de tabla a la izquierda (el resto del texto se justifica)
for tbl in [e for e in bloque if e.tag==w('tbl')]:
    filas=tbl.findall(w('tr'))
    for p in (filas[0].iter(w('p')) if len(filas)>1 else []):
        ppr=p.find(w('pPr'))
        if ppr is None: ppr=etree.SubElement(p,w('pPr')); p.insert(0,ppr)
        for j in ppr.findall(w('jc')): ppr.remove(j)
        jc=etree.Element(w('jc')); jc.set(w('val'),'left')
        orden=['pStyle','keepNext','keepLines','pageBreakBefore','framePr','widowControl','numPr','suppressLineNumbers','pBdr','shd','tabs','suppressAutoHyphens','kinsoku','wordWrap','overflowPunct','topLinePunct','autoSpaceDE','autoSpaceDN','bidi','adjustRightInd','snapToGrid','spacing','ind','contextualSpacing','mirrorIndents','suppressOverlap']
        pos=sum(1 for c in ppr if etree.QName(c).localname in orden)
        ppr.insert(pos,jc)
tree=etree.parse(f'{tmp}/word/document.xml'); body=tree.getroot().find(w('body')); kids=list(body)
sect=[i for i,e in enumerate(kids) if e.tag==w('p') and e.find('.//'+w('sectPr')) is not None][-1]
for i,e in enumerate(bloque): body.insert(sect+i,e)
tree.write(f'{tmp}/word/document.xml', xml_declaration=True, encoding='UTF-8', standalone=True)
if os.path.exists(SALIDA): os.remove(SALIDA)
with zipfile.ZipFile(SALIDA,'w',zipfile.ZIP_DEFLATED) as z:
    for dp,_,fs in os.walk(tmp):
        for f in fs:
            full=os.path.join(dp,f); arc=os.path.relpath(full,tmp)
            z.write(full, arc)
shutil.rmtree(tmp); shutil.rmtree(sgi)
print('listo', SALIDA)
