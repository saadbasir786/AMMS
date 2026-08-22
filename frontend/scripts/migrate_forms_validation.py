from pathlib import Path

path = Path('src/main.tsx')
text = path.read_text()

anchor = "import { RequestListFilters } from '@/components/amms/request-list-filters'\n"
if anchor not in text:
    raise SystemExit('import anchor not found')
text = text.replace(anchor, anchor + "import { InterDirectorateRequestForm, type InterDirectorateFormValue } from '@/components/amms/inter-directorate-request-form'\n", 1)

start = text.index('function CreateInterDirectorateRequest()')
end = text.index('\nfunction Flow02EditPanel', start)
replacement = '''function CreateInterDirectorateRequest() {
  const navigate=useNavigate()
  const [units,setUnits]=useState<any[]>([])
  const [employees,setEmployees]=useState<any[]>([])
  const [error,setError]=useState('')
  useEffect(()=>{
    api.get('/organizational-units').then(r=>setUnits(apiData(r)))
    api.get('/employees').then(r=>setEmployees(apiData(r)))
  },[])
  const initialValue:InterDirectorateFormValue={requesting_unit_id:'',target_unit_id:'',subject:'',purpose:'COORDINATION',description:'',priority:'NORMAL',preferred_date:'',preferred_start_time:'',estimated_duration_minutes:60,proposed_location:'',participant_ids:[]}
  const save=async(form:InterDirectorateFormValue,submit:boolean)=>{
    setError('')
    try{
      const item=apiData(await api.post('/meeting-requests/inter-directorate',form))
      if(submit) await api.post(`/meeting-requests/${item.id}/submit-inter-directorate`)
      navigate(`/meeting-requests/${item.id}`)
    }catch(e:any){
      setError(e.response?.data?.message||'ثبت درخواست بین‌ریاستی ممکن نیست.')
      throw e
    }
  }
  return <>
    <PageTitle eyebrow="درخواست جدید" title="جلسه بین دو ریاست" description="جلسهٔ رسمی میان دو ریاست فعال در یک سازمان." action={<a className="button secondary" href="/meeting-requests">انصراف</a>}/>
    <InterDirectorateRequestForm units={units} employees={employees} purposeLabels={purposeLabels} priorityLabels={priorityLabels} initialValue={initialValue} error={error} onSubmit={save}/>
  </>
}
'''
text = text[:start] + replacement + text[end:]
path.write_text(text)
