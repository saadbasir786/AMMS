export function RouteFallback() {
  return (
    <div className="min-h-dvh bg-background p-4 md:p-6" dir="rtl" aria-busy="true" aria-label="در حال بارگذاری صفحه">
      <div className="mx-auto max-w-[1600px] space-y-6 animate-pulse">
        <div className="h-16 w-2/5 rounded-2xl bg-muted" />
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          <div className="h-96 rounded-2xl border border-border/70 bg-card" />
          <div className="h-64 rounded-2xl border border-border/70 bg-card" />
        </div>
      </div>
    </div>
  )
}
