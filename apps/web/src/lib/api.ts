import axios from 'axios';
export const api=axios.create({baseURL:'/api/v1',headers:{Accept:'application/json'}});
api.interceptors.request.use(c=>{const token=localStorage.getItem('token');if(token)c.headers.Authorization=`Bearer ${token}`;return c});
api.interceptors.response.use(r=>r,e=>{if(e.response?.status===401){localStorage.removeItem('token');if(location.pathname!='/login')location.href='/login'}return Promise.reject(e)});
export const messageOf=(e:any)=>e?.response?.data?.message||Object.values(e?.response?.data?.errors||{})?.flat()?.[0]||e?.message||'Falha inesperada';
export const money=(v:any)=>new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(Number(v||0));
export const dateTime=(v?:string)=>v?new Intl.DateTimeFormat('pt-BR',{dateStyle:'short',timeStyle:'short'}).format(new Date(v)):'—';
