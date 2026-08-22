from pathlib import Path

path = Path('src/main.tsx')
text = path.read_text()

anchor = "import { InterDirectorateRequestForm, type InterDirectorateFormValue } from '@/components/amms/inter-directorate-request-form'\n"
import_line = "import { Flow01DetailView } from '@/components/amms/flow01-detail-view'\n"
if import_line not in text:
    text = text.replace(anchor, anchor + import_line)

start = text.index('function RequestDetail() {')
end = text.index('\n\nfunction Approvals()', start)

replacement = '''function RequestDetail() {
  const id=location.pathname.split('/')[2]
  const {user}=useAuth()
  const [r,setR]=useState<any>(null)
  const navigate=useNavigate()
  const load=()=>api.get(`/meeting-requests/${id}`).then(x=>setR(apiData(x)))
  useEffect(()=>{load()},[id])
  if(!r)return <div className="screen-center"><div className="spinner"/></div>
  if(r.flow_type==='INTER_DIRECTORATE')return <Flow02DetailV2 r={r} reload={load}/>
  if(r.flow_type==='MULTI_DIRECTORATE')return <MultiDirectorateDetail/>
  if(r.flow_type==='DIRECTORATE_WITH_HIGH_AUTHORITY')return <HighAuthorityDetail/>
  if(r.flow_type==='MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY')return <MultiHighAuthorityDetail/>
  if(r.flow_type==='HIGH_AUTHORITY_INITIATED')return <AuthorityInitiatedDetail/>

  const action=async(url:string,body?:any)=>{
    await api.post(`/meeting-requests/${id}/${url}`,body)
    await load()
  }
  const canApprove=!!user?.roles?.some((role:any)=>role.role==='DIRECTOR')
  const canManage=!!user?.roles?.some((role:any)=>role.role==='EXECUTIVE_MANAGEMENT')

  return <>
    <PageTitle
      eyebrow={r.request_number||'پیش‌نویس'}
      title={r.subject}
      description={`${r.requesting_unit?.name||''} · ${purposeLabels[r.purpose]||r.purpose}`}
      action={<div className="page-actions"><StatusBadge value={r.status}/><a className="button secondary" href="/meeting-requests">بازگشت به فهرست</a></div>}
    />
    <Flow01DetailView
      request={r}
      purposeLabels={purposeLabels}
      priorityLabels={priorityLabels}
      canApprove={canApprove}
      canManage={canManage}
      onAction={action}
      onEdit={()=>navigate(`/meeting-requests/${r.id}/edit`)}
      onScheduling={()=>navigate(`/meeting-requests/${r.id}/scheduling`)}
    />
  </>
}'''

text = text[:start] + replacement + text[end:]
path.write_text(text)
