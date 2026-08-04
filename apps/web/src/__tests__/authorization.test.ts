import {beforeEach,describe,expect,it} from 'vitest';
import {createPinia,setActivePinia} from 'pinia';
import {useAuthStore} from '@/stores/auth';

describe('autorização do perfil Auditor Fiscal',()=>{
  beforeEach(()=>setActivePinia(createPinia()));

  it('libera a gestão de clientes conforme as permissões retornadas pela API',()=>{
    const auth=useAuthStore();
    auth.user={
      tenant_id:'11111111-1111-4111-8111-111111111111',
      roles:[{
        name:'Auditor Fiscal',
        permissions:[{name:'clients.view'},{name:'clients.manage'}],
      }],
    };

    expect(auth.isAdmin).toBe(false);
    expect(auth.hasPermission('clients.view')).toBe(true);
    expect(auth.hasPermission('clients.manage')).toBe(true);
    expect(auth.hasPermission('accounts.manage')).toBe(false);
  });

  it('mantém o perfil Consulta somente para leitura',()=>{
    const auth=useAuthStore();
    auth.user={
      tenant_id:'11111111-1111-4111-8111-111111111111',
      roles:[{name:'Consulta',permissions:[{name:'clients.view'}]}],
    };

    expect(auth.hasPermission('clients.view')).toBe(true);
    expect(auth.hasPermission('clients.manage')).toBe(false);
  });
});
