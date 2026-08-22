from pathlib import Path

path = Path('src/main.tsx')
text = path.read_text()

shell_import = "import { ApplicationShell } from '@/components/layout/application-shell'\n"
extra_imports = (
    "import { DashboardOverview } from '@/components/amms/dashboard-overview'\n"
    "import { PageHeader } from '@/components/amms/page-header'\n"
)

if extra_imports not in text:
    if shell_import not in text:
        raise SystemExit('ApplicationShell import marker not found')
    text = text.replace(shell_import, shell_import + extra_imports, 1)

page_start = text.find('function PageTitle(')
dashboard_start = text.find('function Dashboard()', page_start)
if page_start == -1 or dashboard_start == -1:
    raise SystemExit('PageTitle/Dashboard markers not found')

page_wrapper = """function PageTitle({eyebrow,title,description,action}:{eyebrow?:string;title:string;description?:string;action?:React.ReactNode}) {
  return <PageHeader eyebrow={eyebrow} title={title} description={description} action={action}/>
}
"""
text = text[:page_start] + page_wrapper + text[dashboard_start:]

dashboard_start = text.find('function Dashboard()')
status_start = text.find('function StatusBadge', dashboard_start)
if dashboard_start == -1 or status_start == -1:
    raise SystemExit('Dashboard/StatusBadge markers not found after PageTitle migration')

new_dashboard = """function Dashboard() {
  const {user}=useAuth()
  const isSystemAdmin=user?.roles?.some((r:any)=>r.role==='SYSTEM_ADMIN')
  const [data,setData]=useState<any>({})
  const [requests,setRequests]=useState<any[]>([])
  useEffect(()=>{
    api.get('/dashboard').then(r=>setData(apiData(r)))
    api.get('/meeting-requests').then(r=>setRequests(apiPage(r).data||[]))
  },[])
  return <DashboardOverview data={data} requests={requests} canCreateRequest={!isSystemAdmin} statusLabels={statusLabels}/>
}
"""
text = text[:dashboard_start] + new_dashboard + text[status_start:]

path.write_text(text)
print('Page header and dashboard migration applied.')
