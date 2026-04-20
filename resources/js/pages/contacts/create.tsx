import { Head } from "@inertiajs/react";

import ContactController from "@/actions/App/Http/Controllers/ContactController";
import ContactForm, { type ContactFormValues } from "@/components/contacts/contact-form";
import { create as createContact, index as contactsIndex } from "@/routes/contacts";

type Tag = {
  id: number;
  name: string;
  slug: string;
};

type StatusOption = {
  value: "active" | "inactive";
  label: string;
};

export default function CreateContact({
  contact,
  tags,
  statusOptions,
}: {
  contact: ContactFormValues;
  tags: Tag[];
  statusOptions: StatusOption[];
}) {
  return (
    <>
      <Head title="Create Contact" />

      <ContactForm
        title="Create contact"
        description="Add a new person record for the church communications dashboard."
        contact={contact}
        tags={tags}
        statusOptions={statusOptions}
        submitLabel="Create contact"
        submitAction={ContactController.store()}
      />
    </>
  );
}

CreateContact.layout = {
  breadcrumbs: [
    {
      title: "Contacts",
      href: contactsIndex(),
    },
    {
      title: "Create",
      href: createContact(),
    },
  ],
};
