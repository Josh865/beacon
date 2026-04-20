import { Badge } from "@/components/ui/badge";

type CampaignStatus = "draft" | "scheduled" | "processing" | "sent" | "cancelled";

type CampaignStatusBadgeProps = {
  status: CampaignStatus;
};

const badgeClasses: Record<CampaignStatus, string> = {
  draft: "bg-sky-600 text-white hover:bg-sky-600",
  scheduled: "bg-amber-500 text-amber-950 hover:bg-amber-500",
  processing: "bg-indigo-600 text-white hover:bg-indigo-600",
  sent: "bg-emerald-600 text-white hover:bg-emerald-600",
  cancelled: "",
};

const badgeLabels: Record<CampaignStatus, string> = {
  draft: "Draft",
  scheduled: "Scheduled",
  processing: "Processing",
  sent: "Sent",
  cancelled: "Cancelled",
};

export default function CampaignStatusBadge({ status }: CampaignStatusBadgeProps) {
  return (
    <Badge
      variant={status === "cancelled" ? "secondary" : "default"}
      className={badgeClasses[status]}
    >
      {badgeLabels[status]}
    </Badge>
  );
}
