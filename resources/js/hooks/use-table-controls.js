import { useState } from 'react';

/**
 * Search, filter and paginate a list for a table.
 *
 * filters: { key: { label, test(item) } }; the first key is the default.
 *
 * shortcut: runs in the browser, fine for a few hundred rows; move to server-side
 * pagination if a list grows into the thousands.
 */
export function useTableControls(
    items,
    { searchFields, filters = {}, perPage = 10 },
) {
    const [query, setQueryValue] = useState('');
    const [filter, setFilterValue] = useState(Object.keys(filters)[0] ?? '');
    const [page, setPage] = useState(1);

    const needle = query.trim().toLowerCase();
    const test = filters[filter]?.test ?? (() => true);
    const filtered = items.filter(
        (item) =>
            test(item) &&
            (needle === '' ||
                searchFields.some((field) =>
                    String(item[field] ?? '')
                        .toLowerCase()
                        .includes(needle),
                )),
    );

    const pageCount = Math.max(1, Math.ceil(filtered.length / perPage));
    // Deleting rows can shrink the list below the current page.
    const currentPage = Math.min(page, pageCount);

    return {
        query,
        setQuery: (value) => {
            setQueryValue(value);
            setPage(1);
        },
        filter,
        setFilter: (value) => {
            setFilterValue(value);
            setPage(1);
        },
        filterOptions: Object.entries(filters).map(([value, { label }]) => ({
            value,
            label,
        })),
        page: currentPage,
        setPage,
        pageCount,
        perPage,
        filteredCount: filtered.length,
        pageItems: filtered.slice(
            (currentPage - 1) * perPage,
            currentPage * perPage,
        ),
    };
}
