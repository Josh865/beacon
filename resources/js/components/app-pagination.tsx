import { Link } from "@inertiajs/react";

import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination";
import { cn } from "@/lib/utils";

type PaginatorLink = {
  active: boolean;
  label: string;
  url: string | null;
};

export type PaginationData = {
  current_page: number;
  from: number | null;
  links: PaginatorLink[];
  last_page: number;
  next_page_url: string | null;
  prev_page_url: string | null;
  to: number | null;
  total: number;
};

type AppPaginationProps = PaginationData & {
  itemLabel: string;
  className?: string;
};

function sanitizeLabel(label: string): string {
  return label
    .replace(/&laquo;|&raquo;/g, "")
    .replace(/<[^>]+>/g, "")
    .trim();
}

export default function AppPagination({
  className,
  current_page,
  from,
  itemLabel,
  last_page,
  links,
  next_page_url,
  prev_page_url,
  to,
  total,
}: AppPaginationProps) {
  const pageLinks = links.slice(1, -1);

  return (
    <div
      className={cn(
        "flex flex-col gap-3 border-t px-4 py-4 sm:flex-row sm:items-center sm:justify-between",
        className,
      )}
    >
      <p className="text-muted-foreground text-sm">
        Showing {from ?? 0}-{to ?? 0} of {total} {itemLabel}
      </p>

      <div className="flex flex-col gap-3 sm:items-end">
        <span className="text-muted-foreground text-sm">
          Page {current_page} of {last_page}
        </span>

        <Pagination className="mx-0 w-auto justify-start sm:justify-end">
          <PaginationContent>
            <PaginationItem>
              {prev_page_url ? (
                <PaginationPrevious
                  render={<Link href={prev_page_url} preserveScroll prefetch />}
                />
              ) : (
                <PaginationPrevious className="pointer-events-none opacity-50" render={<span />} />
              )}
            </PaginationItem>

            {pageLinks.map((link) => {
              const label = sanitizeLabel(link.label);
              const isEllipsis = label === "..." && link.url === null;

              return (
                <PaginationItem key={`${label}-${link.url ?? "ellipsis"}`}>
                  {isEllipsis ? (
                    <PaginationEllipsis />
                  ) : link.url ? (
                    <PaginationLink
                      isActive={link.active}
                      render={<Link href={link.url} preserveScroll prefetch />}
                    >
                      {label}
                    </PaginationLink>
                  ) : (
                    <PaginationLink
                      isActive={link.active}
                      className="pointer-events-none"
                      render={<span />}
                    >
                      {label}
                    </PaginationLink>
                  )}
                </PaginationItem>
              );
            })}

            <PaginationItem>
              {next_page_url ? (
                <PaginationNext render={<Link href={next_page_url} preserveScroll prefetch />} />
              ) : (
                <PaginationNext className="pointer-events-none opacity-50" render={<span />} />
              )}
            </PaginationItem>
          </PaginationContent>
        </Pagination>
      </div>
    </div>
  );
}
