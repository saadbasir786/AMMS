// AMMS Administrative Notification Audio Synthesizer (Web Audio API)
// Provides a subtle, elegant, low-latency administrative chime without external audio files.

const STORAGE_KEY = 'amms_notification_sound_enabled'

let audioCtx: AudioContext | null = null

function getAudioContext(): AudioContext | null {
  if (typeof window === 'undefined') return null

  if (!audioCtx) {
    const AudioContextClass = window.AudioContext || (window as any).webkitAudioContext
    if (AudioContextClass) {
      audioCtx = new AudioContextClass()
    }
  }

  if (audioCtx && audioCtx.state === 'suspended') {
    // Attempt resume on interaction
    audioCtx.resume().catch(() => {
      // Ignore if browser still restricts autoplay
    })
  }

  return audioCtx
}

// Ensure audio context is primed on first user interaction
if (typeof window !== 'undefined') {
  const unlockAudio = () => {
    if (audioCtx && audioCtx.state === 'suspended') {
      audioCtx.resume().catch(() => {})
    }
    window.removeEventListener('click', unlockAudio)
    window.removeEventListener('keydown', unlockAudio)
  }
  window.addEventListener('click', unlockAudio, { passive: true })
  window.addEventListener('keydown', unlockAudio, { passive: true })
}

/**
 * Check if notification sound is enabled in user settings (default: true).
 */
export function isNotificationSoundEnabled(): boolean {
  if (typeof window === 'undefined') return true
  const saved = localStorage.getItem(STORAGE_KEY)
  return saved !== 'false'
}

/**
 * Update user sound preference in localStorage.
 */
export function setNotificationSoundEnabled(enabled: boolean): void {
  if (typeof window === 'undefined') return
  localStorage.setItem(STORAGE_KEY, enabled ? 'true' : 'false')
}

/**
 * Plays a rich, elegant 3-tone crystalline administrative chime (~1.25s duration).
 * Note 1: E5 (659.25 Hz) - gentle chime intro
 * Note 2: A5 (880.00 Hz) - melodic transition
 * Note 3: C#6 (1108.73 Hz) with subtle E6 (1318.51 Hz) overtone - clear, sustained bell resonance
 *
 * @param force If true, bypasses user preference (useful for sound preview / test).
 */
export function playNotificationChime(force = false): void {
  if (!force && !isNotificationSoundEnabled()) {
    return
  }

  try {
    const ctx = getAudioContext()
    if (!ctx) return

    if (ctx.state === 'suspended') {
      ctx.resume().then(() => playChimeNodes(ctx)).catch(() => {})
    } else {
      playChimeNodes(ctx)
    }
  } catch {
    // Gracefully ignore audio synthesis errors
  }
}

function playChimeNodes(ctx: AudioContext): void {
  const now = ctx.currentTime

  // Master gain node - clear, pleasant audible level (0.22)
  const masterGain = ctx.createGain()
  masterGain.gain.setValueAtTime(0.22, now)
  masterGain.connect(ctx.destination)

  // Tone 1: E5 (659.25 Hz)
  const osc1 = ctx.createOscillator()
  const gain1 = ctx.createGain()
  osc1.type = 'sine'
  osc1.frequency.setValueAtTime(659.25, now)

  gain1.gain.setValueAtTime(0, now)
  gain1.gain.linearRampToValueAtTime(0.85, now + 0.02)
  gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.38)

  osc1.connect(gain1)
  gain1.connect(masterGain)
  osc1.start(now)
  osc1.stop(now + 0.4)

  // Tone 2: A5 (880.00 Hz)
  const osc2 = ctx.createOscillator()
  const gain2 = ctx.createGain()
  osc2.type = 'sine'
  osc2.frequency.setValueAtTime(880.0, now + 0.14)

  gain2.gain.setValueAtTime(0, now)
  gain2.gain.setValueAtTime(0, now + 0.14)
  gain2.gain.linearRampToValueAtTime(0.95, now + 0.16)
  gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.6)

  osc2.connect(gain2)
  gain2.connect(masterGain)
  osc2.start(now + 0.14)
  osc2.stop(now + 0.62)

  // Tone 3: C#6 (1108.73 Hz) - crystalline primary bell note with longer sustain
  const osc3 = ctx.createOscillator()
  const gain3 = ctx.createGain()
  osc3.type = 'sine'
  osc3.frequency.setValueAtTime(1108.73, now + 0.28)

  gain3.gain.setValueAtTime(0, now)
  gain3.gain.setValueAtTime(0, now + 0.28)
  gain3.gain.linearRampToValueAtTime(1.0, now + 0.31)
  gain3.gain.exponentialRampToValueAtTime(0.0005, now + 1.25)

  osc3.connect(gain3)
  gain3.connect(masterGain)
  osc3.start(now + 0.28)
  osc3.stop(now + 1.28)

  // Tone 4: E6 (1318.51 Hz) - soft harmonic overtone for acoustic richness
  const osc4 = ctx.createOscillator()
  const gain4 = ctx.createGain()
  osc4.type = 'sine'
  osc4.frequency.setValueAtTime(1318.51, now + 0.28)

  gain4.gain.setValueAtTime(0, now)
  gain4.gain.setValueAtTime(0, now + 0.28)
  gain4.gain.linearRampToValueAtTime(0.25, now + 0.31)
  gain4.gain.exponentialRampToValueAtTime(0.0005, now + 1.1)

  osc4.connect(gain4)
  gain4.connect(masterGain)
  osc4.start(now + 0.28)
  osc4.stop(now + 1.15)
}
