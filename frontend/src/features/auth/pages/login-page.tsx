import { LoginForm } from '../components/login-form'

type LoginPageProps = {
  onLogin: (email: string, password: string) => Promise<void>
}

export function LoginPage({ onLogin }: LoginPageProps) {
  return (
    <div className="relative flex min-h-screen w-full items-center justify-center overflow-hidden bg-background p-4 text-foreground selection:bg-primary/20">
      <img
        src="/images/login-meeting-background-light.png"
        alt=""
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 size-full scale-105 object-cover object-center saturate-[0.9] dark:opacity-25"
      />
      <div className="pointer-events-none absolute inset-0 bg-gradient-to-r from-background/55 via-background/15 to-transparent dark:from-background/95 dark:via-background/75 dark:to-background/45" />
      <div className="pointer-events-none absolute inset-0 bg-gradient-to-b from-background/5 via-transparent to-background/35 dark:to-background/75" />
      <div className="pointer-events-none absolute inset-y-0 start-0 w-[58%] bg-[radial-gradient(circle_at_35%_50%,color-mix(in_srgb,var(--amms-ui-primary)_10%,transparent),transparent_64%)]" />

      {/* Decorative background grid and ambient lighting */}
      <div className="pointer-events-none fixed inset-0 overflow-hidden opacity-60">
        <div className="absolute -start-40 -top-40 size-[500px] rounded-full bg-primary/10 blur-[120px]" />
        <div className="absolute -bottom-48 -end-32 size-[520px] rounded-full bg-sky-200/25 blur-[130px] dark:bg-sky-900/10" />
        <div className="absolute inset-0 bg-[radial-gradient(color-mix(in_srgb,var(--amms-ui-primary)_8%,transparent)_1px,transparent_1px)] [background-size:24px_24px]" />
      </div>

      <main className="relative z-10 flex w-full max-w-6xl items-center justify-center md:justify-end">
        <LoginForm onLogin={onLogin} />
      </main>
    </div>
  )
}
