from pathlib import Path

path = Path('src/main.tsx')
text = path.read_text()

import_anchor = "import { InterDirectorateRequestForm, type InterDirectorateFormValue } from '@/components/amms/inter-directorate-request-form'\n"
import_line = "import { Flow02DetailView } from '@/components/amms/flow02-detail-view'\n"
if import_line not in text:
    text = text.replace(import_anchor, import_anchor + import_line)

start = text.index('function Flow02Detail({r,reload}:{r:any;reload:()=>void})')
end = text.index('\n\nfunction CreateRequest()', start)

replacement = '''function Flow02Detail({r,reload}:{r:any;reload:()=>void}) {
  const {user}=useAuth()
  const requester=r.units?.find((u:any)=>u.role==='REQUESTER')
  const target=r.units?.find((u:any)=>u.role==='TARGET')
  const canStartScheduling=!!user?.roles?.some((role:any)=>['EXECUTIVE_MANAGEMENT'].includes(role.role)&&[requester?.organizational_unit_id,target?.organizational_unit_id].includes(Number(role.unit?.id||role.organizational_unit_id)))
  const action=async(url:string,body?:any)=>{
    await api.post(`/meeting-requests/${r.id}/${url}`,body)
    reload()
  }
  return <>
    <PageTitle
      eyebrow={`${r.request_number||'پیش‌نویس'} · جلسه بین دو ریاست`}
      title={r.subject}
      description={`${requester?.unit?.name||r.requesting_unit?.name||'—'} ↔ ${target?.unit?.name||'—'}`}
      action={<div className="page-actions"><StatusBadge value={r.status}/><a className="button secondary" href="/meeting-requests">بازگشت به فهرست</a></div>}
    />
    <Flow02DetailView
      request={r}
      requester={requester}
      target={target}
      canStartScheduling={canStartScheduling}
      purposeLabels={purposeLabels}
      priorityLabels={priorityLabels}
      statusLabels={statusLabels}
      onAction={action}
      editPanel={r.status==='RETURNED_BY_TARGET'?<Flow02EditPanel r={r} reload={reload}/>:undefined}
    />
  </>
}'''

text = text[:start] + replacement + text[end:]
path.write_text(text)
