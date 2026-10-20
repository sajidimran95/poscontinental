Official Michigan Treasury Tobacco/Cigarette XML schemas (source: `sample/Michigan Tobacco XML Schemas`).

Only two changes were made so libxml (PHP `DOMDocument::schemaValidate`) can load them:

- `ExtendedCommon/Transmission.xsd`: include paths use `/` instead of `\`.
- `TobCigSchema/MI_TobCigEnumerations.xsd`: removed a duplicated UTF-8 BOM.

Entry point: `ExtendedCommon/Transmission.xsd`.
