from pathlib import Path

path = Path('src/main.tsx')
text = path.read_text()

imports = """import { MeetingRequestsTable } from '@/components/amms/meeting-requests-table'\nimport { RequestListFilters } from '@/components/amms/request-list-filters'\n"""
anchor = "import { PageHeader } from '@/components/amms/page-header'\n"
if imports.strip() not in text:
    if anchor not in text:
        raise SystemExit('PageHeader import anchor not found')
    text = text.replace(anchor, anchor + imports, 1)


def replace_function(source: str, name: str, replacement: str) -> str:
    signature = f'function {name}()'
    start = source.find(signature)
    if start < 0:
        raise SystemExit(f'{signature} not found')
    brace = source.find('{', start)
    if brace < 0:
        raise SystemExit(f'opening brace for {name} not found')
    depth = 0
    end = None
    for index in range(brace, len(source)):
        char = source[index]
        if char == '{':
            depth += 1
        elif char == '}':
            depth -= 1
            if depth == 0:
                end = index + 1
                break
    if end is None:
        raise SystemExit(f'closing brace for {name} not found')
    return source[:start] + replacement.strip() + source[end:]

requests = r'''
function Requests() {
  const [data,setData]=useState<any>({data:[]})
  const [status,setStatus]=useState('')
  const [search,setSearch]=useState('')
  const load=()=>api.get('/meeting-requests',{params:{status:status||undefined,search:search||undefined}}).then(r=>setData(apiPage(r)))
  useEffect(()=>{load()},[status])
  const rows=data.data||[]
  const statusOptions=Object.entries(statusLabels).map(([value,label])=>({value,label}))
  return <>
    <PageTitle
      eyebrow="مدیریت گردش کار"
      title="درخواست‌های جلسه"
      description="درخواست‌های ثبت‌شده در حوزه دسترسی شما."
      action={<div className="page-actions"><a className="button secondary" href="/meeting-requests/create">درخواست داخلی</a><a className="button primary" href="/meeting-requests/create-inter-directorate"><Plus size={18}/> جلسه بین دو ریاست</a><a className="button primary" href="/meeting-requests/create-multi-directorate"><Plus size={18}/> جلسه چندریاستی</a><a className="button primary" href="/meeting-requests/create-high-authority"><Plus size={18}/> جلسه با مقام عالی</a><a className="button primary" href="/meeting-requests/create-multi-high-authority"><Plus size={18}/> چندریاستی با مقام</a><a className="button primary" href="/meeting-requests/create-authority-initiated"><Plus size={18}/> جلسه به ابتکار مقام</a></div>}
    />
    <RequestListFilters
      searchValue={search}
      searchPlaceholder="جستجو بر اساس موضوع یا شماره..."
      onSearchChange={setSearch}
      onSearchSubmit={load}
      statusValue={status}
      statusOptions={statusOptions}
      onStatusChange={setStatus}
      resultCount={rows.length}
    />
    <MeetingRequestsTable
      rows={rows}
      statusLabels={statusLabels}
      priorityLabels={priorityLabels}
      purposeLabels={purposeLabels}
      emptyText="درخواستی با این مشخصات یافت نشد."
    />
  </>
}
'''

incoming = r'''
function IncomingRequests() {
  const [data,setData]=useState<any>({data:[]})
  const [status,setStatus]=useState('')
  const load=()=>Promise.all([
    api.get('/incoming-meeting-requests',{params:{status:status||undefined}}),
    api.get('/incoming-multi-directorate-meeting-requests',{params:{status:status||undefined}})
  ]).then(([a,b])=>{
    const left=apiPage(a).data||[]
    const right=apiPage(b).data||[]
    setData({data:[...left,...right].sort((x:any,y:any)=>String(y.created_at).localeCompare(String(x.created_at)))})
  })
  useEffect(()=>{load()},[status])
  const rows=data.data||[]
  const statusOptions=Object.entries(statusLabels)
    .filter(([key])=>key.includes('TARGET')||key.includes('BILATERAL')||key==='SUBMITTED_TO_TARGET'||key==='UNDER_TARGET_REVIEW')
    .map(([value,label])=>({value,label}))
  return <>
    <PageTitle
      eyebrow="ریاست مقصد"
      title="درخواست‌های وارده"
      description="درخواست‌هایی که پس از تأیید ریاست درخواست‌کننده به حوزهٔ شما ارسال شده‌اند."
    />
    <RequestListFilters
      statusValue={status}
      statusOptions={statusOptions}
      onStatusChange={setStatus}
      resultCount={rows.length}
    />
    <MeetingRequestsTable
      rows={rows}
      mode="incoming"
      statusLabels={statusLabels}
      priorityLabels={priorityLabels}
      emptyText="درخواست وارده‌ای با این مشخصات یافت نشد."
    />
  </>
}
'''

text = replace_function(text, 'Requests', requests)
text = replace_function(text, 'IncomingRequests', incoming)
path.write_text(text)
print('Tables and filters migration applied.')
