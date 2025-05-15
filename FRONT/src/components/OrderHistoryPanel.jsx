import React from 'react';

const OrderHistoryPanel = ({ history }) => {
  const getStatusIcon = (statut) => {
    switch (statut) {
      case 'TERMINEE': return '✅';
      case 'ANNULEE': return '❌';
      case 'ERREUR': return '⚠️';
      default: return 'ℹ️';
    }
  };

  return (
    <div className="order-history-panel component-panel">
      <h3>Historique</h3>
      {(!history || history.length === 0) ? (
        <p className="empty-message">Aucune commande dans l'historique pour le moment.</p>
      ) : (
        <div className="history-list">
          {history.map(order => {
            const statusIcon = getStatusIcon(order.statut);
            return (
              <div key={`${order.id}-${order.dateCreation}`} className={`history-item status-history-${order.statut ? order.statut.toLowerCase() : 'unknown'}`}>
                <span className="history-item-icon">{statusIcon}</span>
                <div className="history-item-summary">
                  <span className="history-item-main">
                    {order.type} {order.taille ? order.taille.toLowerCase() : ''} d'intensité {order.intensite}
                  </span>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};

export default OrderHistoryPanel; 