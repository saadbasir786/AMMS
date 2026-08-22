from pathlib import Path

path = Path('src/flow02.tsx')
text = path.read_text()

import_anchor = "import { ArrowRight, Building2, CalendarDays, CheckCircle2, Paperclip, Save, Users } from 'lucide-react'\n"
import_line = "import { Flow02DetailView } from '@/components/amms/flow02-detail-view'\n"
if import_line not in text:
    text = text.replace(import_anchor, import_anchor + import_line)

start = text.index('export function Flow02DetailV2({ r, reload }: { r: Any; reload: () => void })')
end = text.index('\n\nexport function Flow02SchedulingV2', start)

replacement = '''export function Flow02DetailV2({ r, reload }: { r: Any; reload: () => void }) {
  const user = useFlow02User()
  const requester = r.units?.find((u: Any) => u.role === 'REQUESTER')
  const target = r.units?.find((u: Any) => u.role === 'TARGET')
  const requesterId = Number(requester?.organizational_unit_id)
  const targetId = Number(target?.organizational_unit_id)
  const canRequesterExec = hasScope(user, 'EXECUTIVE_MANAGEMENT', requesterId)
  const canRequesterDirector = hasScope(user, 'DIRECTOR', requesterId)
  const canTargetExec = hasScope(user, 'EXECUTIVE_MANAGEMENT', targetId)
  const canTargetDirector = hasScope(user, 'DIRECTOR', targetId)

  const action = async (url: string, body?: Any) => {
    await api.post(`/meeting-requests/${r.id}/${url}`, body)
    reload()
  }

  return <>
    <PageTitle
      eyebrow={`${r.request_number || 'پیش‌نویس'} · FLOW-02`}
      title={r.subject}
      description={`${requester?.unit?.name || '—'} ↔ ${target?.unit?.name || '—'}`}
      action={<div className="page-actions"><Status value={r.status} /><a className="button secondary" href="/meeting-requests">بازگشت</a></div>}
    />
    <Flow02DetailView
      request={r}
      requester={requester}
      target={target}
      canRequesterExec={canRequesterExec}
      canRequesterDirector={canRequesterDirector}
      canTargetExec={canTargetExec}
      canTargetDirector={canTargetDirector}
      purposeLabels={purposeLabels}
      priorityLabels={priorityLabels}
      onAction={action}
      editPanel={['RETURNED_TO_REQUESTER', 'RETURNED_BY_TARGET'].includes(r.status) && canRequesterExec ? <CorrectionPanel r={r} reload={reload} /> : undefined}
    />
  </>
}'''

text = text[:start] + replacement + text[end:]
path.write_text(text)
