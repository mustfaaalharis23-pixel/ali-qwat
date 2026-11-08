import { useEffect, useState } from 'react';
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { Check, ChevronDown, ChevronsUpDown, Languages, LogOut, Menu as MenuIcon, Monitor, Moon, Search, Sun, X } from 'lucide-react';
import { api } from '../lib/api';
import { cn } from '../lib/format';
import { boot, useI18n } from '../lib/i18n';
import { useLogout, useSession } from '../lib/session';
import { Menu, NavIcon, Skeleton } from '../components/ui';
import { CommandPalette } from './CommandPalette';

interface NavItem {
    key: string;
    label: string;
    icon: string | null;
    path: string;
}

interface NavGroup {
    key: string | null;
    label: string | null;
    items: NavItem[];
}

export function useNavigation() {
    return useQuery({ queryKey: ['navigation'], queryFn: () => api.get<{ groups: NavGroup[] }>('/navigation'), staleTime: 5 * 60_000 });
}

type Theme = 'light' | 'dark' | 'system';

function readTheme(): Theme {
    try {
        const saved = localStorage.getItem('admin-theme');
        return saved === 'light' || saved === 'dark' ? saved : 'system';
    } catch {
        return 'system';
    }
}

/** Light, dark, or the system's choice, kept in this browser only. */
function useTheme() {
    const [theme, setTheme] = useState<Theme>(readTheme);
    useEffect(() => {
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const apply = () => document.documentElement.classList.toggle('dark', theme === 'dark' || (theme === 'system' && media.matches));
        apply();
        try {
            if (theme === 'system') localStorage.removeItem('admin-theme');
            else localStorage.setItem('admin-theme', theme);
        } catch {
            /* the choice is only remembered when the browser allows it */
        }
        media.addEventListener('change', apply);
        return () => media.removeEventListener('change', apply);
    }, [theme]);
    return [theme, setTheme] as const;
}

function useCollapsedGroups() {
    const [collapsed, setCollapsed] = useState<Record<string, boolean>>(() => {
        try {
            return JSON.parse(localStorage.getItem('admin-nav-collapsed') ?? '{"lenses":true}');
        } catch {
            return { lenses: true };
        }
    });
    const toggle = (key: string) =>
        setCollapsed((state) => {
            const next = { ...state, [key]: !state[key] };
            try {
                localStorage.setItem('admin-nav-collapsed', JSON.stringify(next));
            } catch {
                /* remembered only when the browser allows it */
            }
            return next;
        });
    return [collapsed, toggle] as const;
}

function Sidebar({ onNavigate, onSearch }: { onNavigate?: () => void; onSearch: () => void }) {
    const { t } = useI18n();
    const { data, isLoading } = useNavigation();
    const [collapsed, toggle] = useCollapsedGroups();
    const location = useLocation();
    const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);

    return (
        <>
            <div className="px-3 pb-2">
                <button
                    onClick={onSearch}
                    className="flex h-8.5 w-full items-center gap-2 rounded-lg border border-line bg-surface px-2.5 text-[13px] text-faint shadow-[var(--shadow-card)] transition-colors hover:border-line-strong hover:text-muted"
                >
                    <Search className="size-3.75" />
                    <span className="flex-1 text-start">{t('admin.layout.search')}</span>
                    <kbd dir="ltr" className="rounded border border-line bg-subtle px-1.5 text-[10.5px] font-medium">{isMac ? '⌘K' : 'Ctrl K'}</kbd>
                </button>
            </div>
            <nav className="flex flex-1 flex-col gap-4 overflow-y-auto px-3 pt-2 pb-6">
                {isLoading && (
                    <div className="flex flex-col gap-2.5 px-2">
                        {Array.from({ length: 10 }).map((_, index) => (
                            <Skeleton key={index} className="h-4.5" />
                        ))}
                    </div>
                )}
                {data?.groups.map((group, index) => {
                    const key = group.key ?? `g${index}`;
                    const hasActive = group.items.some((item) => item.path !== '/' && location.pathname.startsWith(item.path));
                    const isCollapsed = collapsed[key] && !hasActive;
                    return (
                        <div key={key}>
                            {group.label && (
                                <button onClick={() => toggle(key)} className="group mb-1 flex h-6 w-full items-center justify-between rounded-md px-2.5 text-[11.5px] font-semibold tracking-wide text-faint hover:text-muted">
                                    {group.label}
                                    <ChevronDown className={cn('size-3.5 opacity-0 transition-[transform,opacity] group-hover:opacity-100', isCollapsed && '-rotate-90 opacity-100 rtl:rotate-90')} />
                                </button>
                            )}
                            {!isCollapsed && (
                                <ul className="flex flex-col gap-px">
                                    {group.items.map((item) => (
                                        <li key={item.key}>
                                            <NavLink
                                                to={item.path}
                                                end={item.path === '/'}
                                                onClick={onNavigate}
                                                className={({ isActive }) =>
                                                    cn(
                                                        'group flex h-8.5 items-center gap-2.5 rounded-lg px-2.5 text-[13.5px] transition-colors',
                                                        isActive ? 'bg-surface font-medium text-ink shadow-[var(--shadow-raised)]' : 'text-muted hover:bg-hover hover:text-ink',
                                                    )
                                                }
                                            >
                                                {({ isActive }) => (
                                                    <>
                                                        <NavIcon name={item.icon} className={cn('size-4.25 shrink-0', isActive ? 'text-accent' : 'text-faint group-hover:text-muted')} />
                                                        <span className="truncate">{item.label}</span>
                                                    </>
                                                )}
                                            </NavLink>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    );
                })}
            </nav>
        </>
    );
}

function Brand() {
    const { t } = useI18n();
    return (
        <div className="flex h-15 items-center gap-2.5 px-4.5">
            <div className="flex size-8 items-center justify-center rounded-[9px] bg-gradient-to-br from-accent to-[oklch(0.45_0.22_290)] text-[13px] font-bold text-white shadow-[inset_0_1px_0_oklch(1_0_0/0.2),0_2px_6px_oklch(0.4_0.2_270/0.35)]">
                {boot.appName.slice(0, 2)}
            </div>
            <div className="min-w-0 leading-tight">
                <p className="truncate text-[14px] font-semibold tracking-tight">{boot.appName}</p>
                <p className="truncate text-[11.5px] text-faint">{t('admin.layout.subtitle')}</p>
            </div>
        </div>
    );
}

function UserMenu() {
    const { t, locale, locales, setLocale } = useI18n();
    const { data: session } = useSession();
    const logout = useLogout();
    const navigate = useNavigate();
    const [theme, setTheme] = useTheme();
    const user = session?.user;

    const themeItem = (value: Theme, label: string, Icon: typeof Sun) => ({
        label: (
            <span className="flex flex-1 items-center justify-between gap-3">
                {label}
                {theme === value && <Check className="size-3.5 text-accent" />}
            </span>
        ),
        icon: <Icon className="size-4 text-muted" />,
        onSelect: () => setTheme(value),
    });

    return (
        <div className="border-t border-line p-3">
            <Menu
                align="start"
                placement="top"
                trigger={(toggle) => (
                    <button onClick={toggle} className="flex w-full items-center gap-2.5 rounded-lg p-1.5 text-start hover:bg-hover">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-[13px] font-semibold text-accent ring-1 ring-accent/15">
                            {user?.name.slice(0, 1).toUpperCase()}
                        </span>
                        <span className="min-w-0 flex-1 leading-tight">
                            <span className="block truncate text-[13px] font-medium">{user?.name}</span>
                            <span className="block truncate text-[11.5px] text-faint">{user?.email}</span>
                        </span>
                        <ChevronsUpDown className="size-4 text-faint" />
                    </button>
                )}
                items={[
                    ...Object.entries(locales).map(([value, label]) => ({
                        label: (
                            <span className="flex flex-1 items-center justify-between gap-3">
                                {label}
                                {value === locale && <Check className="size-3.5 text-accent" />}
                            </span>
                        ),
                        icon: <Languages className="size-4 text-muted" />,
                        onSelect: () => setLocale(value),
                    })),
                    { divider: true, label: '' },
                    themeItem('light', t('admin.layout.theme_light'), Sun),
                    themeItem('dark', t('admin.layout.theme_dark'), Moon),
                    themeItem('system', t('admin.layout.theme_system'), Monitor),
                    { divider: true, label: '' },
                    {
                        label: t('admin.layout.logout'),
                        icon: <LogOut className="size-4" />,
                        tone: 'danger' as const,
                        onSelect: () => logout.mutate(undefined, { onSuccess: () => navigate('/login') }),
                    },
                ]}
            />
        </div>
    );
}

export function AppLayout() {
    const { t } = useI18n();
    const location = useLocation();
    const [drawer, setDrawer] = useState(false);
    const [palette, setPalette] = useState(false);

    useEffect(() => {
        setDrawer(false);
    }, [location.pathname]);

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setPalette((open) => !open);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    return (
        <div className="flex min-h-dvh">
            <aside className="sticky top-0 hidden h-dvh w-[260px] shrink-0 flex-col border-e border-line bg-sidebar lg:flex">
                <Brand />
                <Sidebar onSearch={() => setPalette(true)} />
                <UserMenu />
            </aside>

            {drawer && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div className="absolute inset-0 bg-ink/30 backdrop-blur-[2px]" onClick={() => setDrawer(false)} />
                    <aside className="absolute inset-y-0 start-0 flex w-[280px] flex-col bg-sidebar shadow-[var(--shadow-pop)]">
                        <div className="flex items-center justify-between pe-3">
                            <Brand />
                            <button onClick={() => setDrawer(false)} className="rounded-md p-1.5 text-muted hover:bg-hover" aria-label={t('admin.actions.close')}>
                                <X className="size-5" />
                            </button>
                        </div>
                        <Sidebar onNavigate={() => setDrawer(false)} onSearch={() => setPalette(true)} />
                        <UserMenu />
                    </aside>
                </div>
            )}

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-30 flex h-14 items-center gap-2 border-b border-line bg-surface/85 px-4 backdrop-blur lg:hidden">
                    <button onClick={() => setDrawer(true)} className="rounded-md p-1.5 text-muted hover:bg-hover" aria-label={t('admin.layout.menu')}>
                        <MenuIcon className="size-5" />
                    </button>
                    <span className="flex-1 text-[14px] font-semibold">{boot.appName}</span>
                    <button onClick={() => setPalette(true)} className="rounded-md p-1.5 text-muted hover:bg-hover" aria-label={t('admin.layout.search')}>
                        <Search className="size-5" />
                    </button>
                </header>
                <main className="mx-auto w-full max-w-[1440px] flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
                    <Outlet />
                </main>
            </div>
            <CommandPalette open={palette} onClose={() => setPalette(false)} />
        </div>
    );
}
