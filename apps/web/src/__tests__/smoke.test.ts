import {describe,it,expect} from 'vitest';
import {formatXml} from '@/lib/api';
describe('Auditor Fiscal web',()=>{it('possui ambiente de testes',()=>expect('IBS/CBS').toContain('IBS'))});
describe('visualização XML',()=>{it('formata a hierarquia sem alterar conteúdo textual',()=>{const result=formatXml('<?xml version="1.0"?><NFe><infNFe Id="NFe1"><total>10</total></infNFe></NFe>');expect(result).toContain('\n  <infNFe');expect(result).toContain('\n    <total>10</total>');expect(result.replace(/\s+/g,'')).toBe('<?xmlversion="1.0"?><NFe><infNFeId="NFe1"><total>10</total></infNFe></NFe>')})});
