// @vitest-environment jsdom

import {mount} from '@vue/test-utils';
import {describe,expect,it} from 'vitest';
import AnalysisActionDialog from '@/components/AnalysisActionDialog.vue';
import StatusBadge from '@/components/StatusBadge.vue';

describe('ciclo de vida da auditoria',()=>{
  it('explica a exclusão reversível e envia o motivo normalizado',async()=>{
    const wrapper=mount(AnalysisActionDialog,{props:{action:'delete',name:'Competência 06/2026'}});

    expect(wrapper.text()).toContain('XMLs, DANFEs, achados e logs permanecerão preservados');
    await wrapper.get('textarea').setValue('  organização operacional  ');
    await wrapper.get('form').trigger('submit');

    expect(wrapper.emitted('confirm')?.[0]).toEqual(['organização operacional']);
  });

  it('apresenta estados de cancelamento em português',()=>{
    expect(mount(StatusBadge,{props:{value:'cancelling'}}).text()).toBe('Cancelando');
    expect(mount(StatusBadge,{props:{value:'cancelled'}}).text()).toBe('Cancelada');
  });
});
