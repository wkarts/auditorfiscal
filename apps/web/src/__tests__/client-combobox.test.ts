// @vitest-environment jsdom

import {mount} from '@vue/test-utils';
import {describe,expect,it} from 'vitest';
import ClientCombobox from '@/components/ClientCombobox.vue';

const clients=[
  {id:'alpha',legal_name:'ALFA VEÍCULOS LTDA',trade_name:'ALFA VEÍCULOS',tax_id:'11111111111111'},
  {id:'beta',legal_name:'BETA COMÉRCIO LTDA',trade_name:'BETA SHOP',tax_id:'22222222222222'},
];

describe('seletor pesquisável de clientes',()=>{
  it('localiza por nome sem diferenciar acentos e seleciona pelo teclado',async()=>{
    const wrapper=mount(ClientCombobox,{props:{modelValue:'alpha',options:clients}});
    const input=wrapper.get('input');

    await input.trigger('focus');
    await input.setValue('comercio');

    expect(wrapper.findAll('[role="option"]')).toHaveLength(1);
    expect(wrapper.text()).toContain('BETA SHOP');
    await input.trigger('keydown',{key:'Enter'});
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['beta']);
  });

  it('localiza pelo nome fantasia quando ele está cadastrado',async()=>{
    const wrapper=mount(ClientCombobox,{props:{modelValue:'',options:clients}});
    const input=wrapper.get('input');

    await input.trigger('focus');
    await input.setValue('beta shop');

    expect(wrapper.findAll('[role="option"]')).toHaveLength(1);
    expect(wrapper.text()).toContain('BETA SHOP');
    expect(wrapper.text()).toContain('BETA COMÉRCIO LTDA');
  });

  it('localiza CNPJ digitado com pontuação e informa a pesquisa remota',async()=>{
    const wrapper=mount(ClientCombobox,{props:{modelValue:'',options:clients}});
    const input=wrapper.get('input');

    await input.trigger('focus');
    await input.setValue('22.222.222/2222-22');

    expect(wrapper.findAll('[role="option"]')).toHaveLength(1);
    expect(wrapper.text()).toContain('22222222222222');
    expect(wrapper.emitted('search')?.[0]).toEqual(['22.222.222/2222-22']);
  });
});
