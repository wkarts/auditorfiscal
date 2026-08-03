from datetime import datetime
from decimal import Decimal
from collections import defaultdict
from .xml_parser import finding

def apply_cross_document_rules(documents:list[dict])->list[dict]:
    findings=[];by_chassis=defaultdict(list)
    for d in documents:
        for i in d['items']:
            if i.get('chassis'):by_chassis[i['chassis']].append((d,i))
    for chassis,rows in by_chassis.items():
        outputs=[x for x in rows if x[0]['direction']=='saida'];inputs=[x for x in rows if x[0]['direction']=='entrada']
        outputs.sort(key=lambda x:x[0].get('issued_at') or '')
        for a,b in zip(outputs,outputs[1:]):
            da,db=a[0],b[0]
            try:minutes=abs((datetime.fromisoformat(db['issued_at'])-datetime.fromisoformat(da['issued_at'])).total_seconds())/60
            except:minutes=999999
            same_party=da.get('recipient_tax_id')==db.get('recipient_tax_id')
            values_close=abs(Decimal(da['total_value'])-Decimal(db['total_value']))<=Decimal('1000')
            if same_party and values_close and minutes<=60:
                ev={'chassis':chassis,'documents':[da['number'],db['number']],'minutes':round(minutes,2),'values':[da['total_value'],db['total_value']],'recipient_tax_id':da.get('recipient_tax_id')}
                findings.append(finding('DUPLICATE-ECONOMIC-001','critical','duplicate','Possível duplicidade econômica',f'NF {da["number"]} e NF {db["number"]} apresentam mesmo chassi, contraparte e emissão próxima.',da['document_ref'],None,ev,'Pode superestimar faturamento, bases e débitos.','Consultar eventos oficiais de cancelamento na SEFAZ.',Decimal('0.90')))
        for out_doc,out_item in outputs:
            prior=[x for x in inputs if (x[0].get('issued_at') or '') <= (out_doc.get('issued_at') or '')]
            if not prior:
                findings.append(finding('RECONCILIATION-MISSING-INPUT-001','medium','data_quality','Entrada anterior não localizada','Não foi localizado, na amostra, documento de entrada anterior para o chassi vendido.',out_doc['document_ref'],out_item['item_number'],{'layer':'economic','chassis':chassis,'output_nf':out_doc['number'],'sample_limitation':True},'Impede conciliar custo, margem e eventual crédito presumido.','Confirmar se a entrada está fora do período/amostra e importar o XML correspondente.'))
                continue
            in_doc,in_item=sorted(prior,key=lambda x:x[0].get('issued_at') or '')[-1]
            cost=Decimal(in_item['product_value']);sale=Decimal(out_item['product_value']);margin=sale-cost;pis_cof=Decimal(out_item.get('pis_cofins') or '0')
            if margin < 0:
                findings.append(finding('NEGATIVE-MARGIN-001','high','economic','Venda abaixo do custo documental','A saída apresenta valor inferior ao custo da última entrada conciliada por chassi.',out_doc['document_ref'],out_item['item_number'],{'layer':'economic','chassis':chassis,'input_nf':in_doc['number'],'output_nf':out_doc['number'],'cost':str(cost),'sale':str(sale),'margin':str(margin)},'Pode indicar prejuízo, custo incompleto ou conciliação documental incorreta.','Validar o custo, as despesas agregadas, devoluções e a correspondência do chassi.'))
            elif margin == 0:
                findings.append(finding('ZERO-MARGIN-001','medium','economic','Margem documental zerada','Entrada e saída conciliadas apresentam o mesmo valor de produto.',out_doc['document_ref'],out_item['item_number'],{'layer':'economic','chassis':chassis,'input_nf':in_doc['number'],'output_nf':out_doc['number'],'cost':str(cost),'sale':str(sale)},'Pode sinalizar operação sem margem ou custo/receita incompletos na amostra.','Revisar descontos, despesas, bonificações e composição do preço.'))
            # Prefer the base explicitly informed in the XML. Infer by the combined 3.65% rate
            # only for older documents/fixtures that do not expose vBC in the normalized item.
            explicit_base=Decimal(out_item.get('pis_cofins_base') or '0')
            pis_cof_base=explicit_base if explicit_base else ((pis_cof/Decimal('0.0365')).quantize(Decimal('0.01')) if pis_cof else Decimal('0'))
            if pis_cof and abs(pis_cof_base-margin)>Decimal('1.00'):
                findings.append(finding('USED-VEHICLE-MARGIN-001','high','margin','Margem PIS/COFINS divergente','A base inferida de PIS/COFINS não coincide com a margem documental entrada × saída.',out_doc['document_ref'],out_item['item_number'],{'chassis':chassis,'input_nf':in_doc['number'],'output_nf':out_doc['number'],'cost':str(cost),'sale':str(sale),'document_margin':str(margin),'inferred_pis_cofins_base':str(pis_cof_base),'difference':str(pis_cof_base-margin)},'Altera PIS/COFINS e, por consequência, a base IBS/CBS.','Reprocessar o custo por chassi e verificar ajustes documentais.'))
    return findings
