/**
 * The PITON backdrop: a gold top stripe and a faint grid that fades toward the edges.
 * Decorative only. The parent needs `relative isolate overflow-hidden`.
 */
export default function BrandBackdrop() {
    return (
        <>
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 -z-10 bg-[linear-gradient(to_right,var(--border)_1px,transparent_1px),linear-gradient(to_bottom,var(--border)_1px,transparent_1px)] [mask-image:radial-gradient(ellipse_at_center,black_20%,transparent_70%)] bg-[size:44px_44px]"
            />
            <div
                aria-hidden="true"
                className="absolute inset-x-0 top-0 h-1.5 bg-primary"
            />
        </>
    );
}
