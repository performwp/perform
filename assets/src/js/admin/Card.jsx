import { Card, CardHeader, CardBody } from '@wordpress/components';

const SettingsCard = ({ title, description, children }) => (
  <Card style={{ marginBottom: '24px' }}>
    <CardHeader>
      <strong>{title}</strong>
    </CardHeader>
    {description && <CardBody><p>{description}</p></CardBody>}
    <CardBody>{children}</CardBody>
  </Card>
);

export default SettingsCard;
