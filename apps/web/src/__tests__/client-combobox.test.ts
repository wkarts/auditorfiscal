// @vitest-environment jsdom

import {mount} from '@vue/test-utils';
import {describe,expect,it} from 'vitest';
import ClientCombobox from '@/components/ClientCombobox.vue';

const clients=[
  {id:'edycar',legal_name:'EDYCAR VEÍCULOS LTDA',trade_name:'EDYCAR VEÍCULOS',tax_id:'27330569000171'},
  {id:'multicar',legal_name:'MULTICAR COMÉRCIO DE VEÍCULOS LTDA',trade_name:'MULTICAR VEÍCULOS',tax_id:'06064829000134'},
];

describe('seletor pesquisável de clientes',()=>{
  it('localiza por nome sem diferenciar acentos e seleciona pelo teclado',async()=>{
    const wrapper=mount(ClientCombobox,{props:{modelValue:'edycar',options:clients}});
    const input=wrapper.get('input');

    await input.trigger('focus');
    await input.setValue('comercio');

    expect(wrapper.findAll('[role="option"]')).toHaveLength(1);
    expect(wrapper.text()).toContain('MULTICAR VEÍCULOS');
    await input.trigger('keydown',{key:'Enter'});
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['multicar']);
  });

  it('localiza CNPJ digitado com pontuação e informa a pesquisa remota',async()=>{
    const wrapper=mount(ClientCombobox,{props:{modelValue:'',options:clients}});
    const input=wrapper.get('input');

    await input.trigger('focus');
    await input.setValue('06.064.829/0001-34');

    expect(wrapper.findAll('[role="option"]')).toHaveLength(1);
    expect(wrapper.text()).toContain('06064829000134');
    expect(wrapper.emitted('search')?.[0]).toEqual(['06.064.829/0001-34']);
  });
});
