import { Button, Spinner } from '@wordpress/components';
import { useEffect } from '@wordpress/element';

const Footer = ({ dirty, saving, message, onSave }) => {
  // message: { text, type } where type is 'success' | 'error' | ''
  return (
    <div className="perform-savebar" style={{
      position: 'sticky',
      bottom: 0,
      left: 0,
      right: 0,
      background: '#fff',
      borderTop: '1px solid #eee',
      padding: 12,
      zIndex: 1000,
      display: 'flex',
      justifyContent: 'flex-end',
      gap: 12,
      alignItems: 'center'
    }}>
      <div style={{ marginRight: 'auto' }} />
      {message && message.text && (
        <div style={{ marginRight: 12, color: message.type === 'error' ? '#c00' : '#146e00' }}>
          {message.text}
        </div>
      )}
      <Button isPrimary onClick={onSave} disabled={!dirty || saving}>
        {saving ? <><Spinner /> Saving...</> : 'Save Settings'}
      </Button>
    </div>
  );
};

export default Footer;
