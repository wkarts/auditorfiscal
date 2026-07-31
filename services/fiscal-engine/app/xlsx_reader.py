from pathlib import Path
from zipfile import ZipFile
from lxml import etree
import re
NS={'a':'http://schemas.openxmlformats.org/spreadsheetml/2006/main','r':'http://schemas.openxmlformats.org/officeDocument/2006/relationships'}
def _col_index(col:str)->int:
    n=0
    for c in col:n=n*26+ord(c)-64
    return n-1

def read_xlsx(path:Path)->dict[str,list[list[str]]]:
    with ZipFile(path) as z:
        shared=[]
        if 'xl/sharedStrings.xml' in z.namelist():
            root=etree.fromstring(z.read('xl/sharedStrings.xml'))
            for si in root.xpath('//a:si',namespaces=NS):shared.append(''.join(si.itertext()))
        wb=etree.fromstring(z.read('xl/workbook.xml'));rels=etree.fromstring(z.read('xl/_rels/workbook.xml.rels'))
        relmap={r.get('Id'):r.get('Target') for r in rels}
        result={}
        for sh in wb.xpath('//a:sheets/a:sheet',namespaces=NS):
            name=sh.get('name');target=relmap[sh.get('{%s}id'%NS['r'])]
            if not target.startswith('xl/'):target='xl/'+target.lstrip('/')
            root=etree.fromstring(z.read(target));rows=[]
            for row in root.xpath('//a:sheetData/a:row',namespaces=NS):
                cells={}
                for c in row.xpath('./a:c',namespaces=NS):
                    col=_col_index(re.match(r'([A-Z]+)',c.get('r')).group(1));typ=c.get('t');value=''
                    if typ=='inlineStr':value=''.join(c.itertext())
                    else:
                        v=c.find('{%s}v'%NS['a'])
                        if v is not None:value=shared[int(v.text)] if typ=='s' else (v.text or '')
                    cells[col]=value
                width=max(cells,default=-1)+1;rows.append([cells.get(i,'') for i in range(width)])
            result[name]=rows
        return result
