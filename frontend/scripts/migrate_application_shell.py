from pathlib import Path
import re

path = Path('src/main.tsx')
text = path.read_text(encoding='utf-8')

import_line = "import { ApplicationShell } from '@/components/layout/application-shell'\n"
if import_line not in text:
    marker = "import './styles.css'\n"
    if marker not in text:
        raise SystemExit('styles import marker not found')
    text = text.replace(marker, marker + import_line, 1)

pattern = re.compile(
    r"function Shell\(\{children\}:\{children:React\.ReactNode\}\) \{.*?\}\nfunction PageTitle",
    re.DOTALL,
)
replacement = """function Shell({children}:{children:React.ReactNode}) {
  const {user,logout}=useAuth()
  const location=useLocation()
  const navigate=useNavigate()
  return <ApplicationShell user={user} navItems={navItems} currentPath={location.pathname} onNavigate={(target)=>navigate(target)} onLogout={logout}>{children}</ApplicationShell>
}
function PageTitle"""

updated, count = pattern.subn(replacement, text, count=1)
if count != 1:
    raise SystemExit(f'expected to replace one Shell function, replaced {count}')

path.write_text(updated, encoding='utf-8')
print('ApplicationShell migration applied.')
