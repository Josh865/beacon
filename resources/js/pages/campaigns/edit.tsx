import { Head } from "@inertiajs/react";

import CampaignController from "@/actions/App/Http/Controllers/CampaignController";
import CampaignForm, {
  type CampaignAudiencePreview,
  type CampaignFormValues,
} from "@/components/campaigns/campaign-form";
import { index as campaignsIndex } from "@/routes/campaigns";

type Tag = {
  id: number;
  name: string;
  slug: string;
};

type AudienceTypeOption = {
  value: "all_contacts" | "tag_selection" | "manual_selection";
  label: string;
  implemented: boolean;
};

type EditCampaignValues = CampaignFormValues & {
  id: number;
};

type EditCampaignProps = {
  campaign: EditCampaignValues;
  tags: Tag[];
  audienceTypes: AudienceTypeOption[];
  audiencePreview: CampaignAudiencePreview;
};

export default function EditCampaign({
  campaign,
  tags,
  audienceTypes,
  audiencePreview,
}: EditCampaignProps) {
  return (
    <>
      <Head title={`Edit ${campaign.name}`} />

      <CampaignForm
        title="Edit campaign"
        description="Refine the message and segment while this campaign is still a draft."
        campaign={campaign}
        tags={tags}
        audienceTypes={audienceTypes}
        audiencePreview={audiencePreview}
        submitLabel="Save changes"
        cancelHref={CampaignController.show(campaign.id).url}
        submitAction={CampaignController.update(campaign.id)}
      />
    </>
  );
}

EditCampaign.layout = {
  breadcrumbs: [
    {
      title: "Campaigns",
      href: campaignsIndex(),
    },
  ],
};
