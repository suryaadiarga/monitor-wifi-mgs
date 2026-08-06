"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { Icon } from "@/components/icon";
import { ThemeToggle } from "@/components/theme-toggle";
import { ApiError, authService } from "@/lib/api";

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState("admin@isp.local");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [remember, setRemember] = useState(true);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setLoading(true);
    try {
      await authService.login(email, password);
      router.push("/dashboard");
      router.refresh();
    } catch (loginError) {
      setError(loginError instanceof ApiError ? loginError.message : "Login gagal. Silakan coba lagi.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="relative min-h-screen overflow-hidden bg-slate-50 dark:bg-slate-950">
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(34,197,94,0.14),transparent_30%),radial-gradient(circle_at_85%_75%,rgba(14,165,233,0.12),transparent_32%)]" />
      <div className="absolute right-5 top-5 z-20 sm:right-8 sm:top-8"><ThemeToggle /></div>

      <div className="relative z-10 mx-auto grid min-h-screen max-w-7xl items-center gap-12 px-5 py-20 lg:grid-cols-[1.08fr_0.92fr] lg:px-10 xl:px-16">
        <section className="hidden lg:block">
          <div className="mb-10 inline-flex items-center gap-3">
            <span className="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-600 text-white shadow-lg shadow-emerald-600/25"><Icon name="wifi" className="h-7 w-7" /></span>
            <div><p className="text-xl font-bold tracking-tight text-slate-950 dark:text-white">ISP Terpadu</p><p className="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-600 dark:text-emerald-400">Network Operations</p></div>
          </div>
          <h1 className="max-w-xl text-5xl font-bold leading-[1.08] tracking-[-0.045em] text-slate-950 dark:text-white xl:text-6xl">
            Kelola seluruh jaringan dalam <span className="text-emerald-600 dark:text-emerald-400">satu kendali.</span>
          </h1>
          <p className="mt-6 max-w-xl text-lg leading-8 text-slate-600 dark:text-slate-400">Pantau router, OLT, ONT, pelanggan, RADIUS, dan layanan VPN secara terpadu dari satu dashboard operasional.</p>
          <div className="mt-10 grid max-w-xl grid-cols-3 gap-4">
            {[
              ["99,8%", "Uptime jaringan"],
              ["24/7", "Monitoring aktif"],
              ["2.4K+", "Pelanggan dikelola"],
            ].map(([value, label]) => (
              <div className="rounded-2xl border border-white/60 bg-white/70 p-4 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/60" key={label}>
                <p className="text-2xl font-bold text-slate-950 dark:text-white">{value}</p><p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{label}</p>
              </div>
            ))}
          </div>
        </section>

        <section className="mx-auto w-full max-w-md">
          <div className="mb-8 flex items-center gap-3 lg:hidden">
            <span className="grid h-11 w-11 place-items-center rounded-2xl bg-emerald-600 text-white shadow-lg shadow-emerald-600/25"><Icon name="wifi" className="h-6 w-6" /></span>
            <div><p className="text-lg font-bold text-slate-950 dark:text-white">ISP Terpadu</p><p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-emerald-600">Network Operations</p></div>
          </div>
          <div className="rounded-[28px] border border-slate-200/80 bg-white p-6 shadow-2xl shadow-slate-900/[0.07] dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/25 sm:p-9">
            <div className="mb-8">
              <p className="mb-2 text-sm font-semibold text-emerald-600 dark:text-emerald-400">Selamat datang kembali</p>
              <h2 className="text-3xl font-bold tracking-tight text-slate-950 dark:text-white">Masuk ke dashboard</h2>
              <p className="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Gunakan akun operasional Anda untuk melanjutkan.</p>
            </div>

            {error && <div role="alert" className="mb-5 flex gap-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300"><Icon name="alert" className="mt-0.5 h-4 w-4 shrink-0" />{error}</div>}

            <form className="space-y-5" onSubmit={handleSubmit}>
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-300">Alamat email</span>
                <div className="relative"><Icon name="users" className="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" /><input autoComplete="email" className="h-12 w-full rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-4 text-sm text-slate-950 transition placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10 dark:border-slate-700 dark:bg-slate-800/70 dark:text-white dark:focus:border-emerald-500 dark:focus:bg-slate-800" onChange={(event) => setEmail(event.target.value)} placeholder="nama@perusahaan.com" required type="email" value={email} /></div>
              </label>
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-300">Kata sandi</span>
                <div className="relative"><Icon name="key" className="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" /><input autoComplete="current-password" className="h-12 w-full rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-12 text-sm text-slate-950 transition placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10 dark:border-slate-700 dark:bg-slate-800/70 dark:text-white dark:focus:border-emerald-500 dark:focus:bg-slate-800" minLength={6} onChange={(event) => setPassword(event.target.value)} placeholder="Masukkan kata sandi" required type={showPassword ? "text" : "password"} value={password} /><button aria-label={showPassword ? "Sembunyikan kata sandi" : "Tampilkan kata sandi"} className="absolute right-3 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700 dark:hover:text-white" onClick={() => setShowPassword((current) => !current)} type="button"><Icon name={showPassword ? "eyeOff" : "eye"} className="h-5 w-5" /></button></div>
              </label>
              <div className="flex items-center justify-between gap-3 text-sm">
                <label className="flex cursor-pointer items-center gap-2 text-slate-600 dark:text-slate-400"><input checked={remember} className="h-4 w-4 rounded border-slate-300 accent-emerald-600" onChange={(event) => setRemember(event.target.checked)} type="checkbox" />Ingat saya</label>
                <button className="font-semibold text-emerald-600 hover:text-emerald-700 dark:text-emerald-400" type="button">Lupa kata sandi?</button>
              </div>
              <button className="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white shadow-lg shadow-emerald-600/20 transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-70" disabled={loading} type="submit">
                {loading ? <><span className="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" />Memverifikasi...</> : <>Masuk ke Dashboard<Icon name="chevron" className="h-4 w-4" /></>}
              </button>
            </form>

            <div className="mt-6 rounded-xl bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-500 dark:bg-slate-800/60 dark:text-slate-400"><span className="font-semibold text-slate-700 dark:text-slate-300">Mode demo:</span> jika backend belum aktif, formulir tetap membuka dashboard menggunakan data simulasi.</div>
          </div>
          <p className="mt-6 text-center text-xs text-slate-400">Akses dilindungi enkripsi dan audit aktivitas sistem.</p>
        </section>
      </div>
    </main>
  );
}
