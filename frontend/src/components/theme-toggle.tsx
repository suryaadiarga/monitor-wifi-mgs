"use client";

export function ThemeToggle({ compact = false }: { compact?: boolean }) {
  function toggleTheme() {
    const isDark = document.documentElement.classList.toggle("dark");
    localStorage.setItem("isp-theme", isDark ? "dark" : "light");
  }

  return (
    <button
      aria-label="Ganti tema tampilan"
      className={`group inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-emerald-300 hover:text-emerald-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-emerald-700 dark:hover:text-emerald-400 ${compact ? "h-10 w-10" : "h-11 w-11"}`}
      onClick={toggleTheme}
      type="button"
    >
      <svg className="h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.8">
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" strokeLinecap="round" />
      </svg>
      <svg className="hidden h-5 w-5 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.8">
        <path d="M21 15.5A9 9 0 1 1 8.5 3 7 7 0 0 0 21 15.5Z" strokeLinecap="round" strokeLinejoin="round" />
      </svg>
    </button>
  );
}
