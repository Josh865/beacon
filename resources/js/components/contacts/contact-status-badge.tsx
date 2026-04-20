import { Badge } from "@/components/ui/badge";

type ContactStatusBadgeProps = {
  status: "active" | "inactive";
};

export default function ContactStatusBadge({ status }: ContactStatusBadgeProps) {
  return (
    <Badge
      variant={status === "active" ? "default" : "secondary"}
      className={status === "active" ? "bg-emerald-600 text-white hover:bg-emerald-600" : ""}
    >
      {status === "active" ? "Active" : "Inactive"}
    </Badge>
  );
}
