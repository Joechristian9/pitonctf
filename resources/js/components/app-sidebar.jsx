import { Link, usePage } from '@inertiajs/react';
import { Flag, ShieldCheck, Swords, Trophy, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { challenges, scoreboard } from '@/routes';
import { game as adminGame } from '@/routes/admin';
import { index as adminTeams } from '@/routes/admin/teams';
import { index as adminUsers } from '@/routes/admin/users';

const mainNavItems = [
    {
        title: 'Challenges',
        href: challenges(),
        icon: Flag,
        // Admins manage challenges from Admin instead.
        isStudentOnly: true,
    },
    {
        title: 'Scoreboard',
        href: scoreboard(),
        icon: Trophy,
    },
];

const adminNavItems = [
    {
        title: 'Admin',
        href: adminGame(),
        icon: ShieldCheck,
    },
    {
        title: 'Teams',
        href: adminTeams(),
        icon: Swords,
    },
    {
        title: 'Students',
        href: adminUsers(),
        icon: Users,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const navItems = auth.isAdmin
        ? [
              ...mainNavItems.filter((item) => !item.isStudentOnly),
              ...adminNavItems,
          ]
        : mainNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={challenges()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
