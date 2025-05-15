import React from 'react';

const PendingOrdersPanel = ({ pendingOrders, handleCancelOrder, cancellingOrderId }) => {
  return (
    <div className="pending-orders-panel component-panel">
      <h3>Commandes en Attente</h3>
      {(!pendingOrders || pendingOrders.length === 0) ? (
        <p className="empty-message">Aucune commande en attente pour le moment.</p>
      ) : (
        <table className="orders-table pending-orders-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Type</th>
              <th>Taille</th>
              <th>Intensité</th>
              <th>Statut</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {pendingOrders.map(order => {
              const statusText = order.statut ? order.statut.toLowerCase() : 'unknown';
              return (
                <tr key={order.id} className={`order-row status-row-${statusText}`}>
                  <td>{order.id}</td>
                  <td>{order.type}</td>
                  <td>{order.taille}</td>
                  <td>{order.intensite}</td>
                  <td>
                    <span className={`order-status-badge status-badge-${statusText}`}>
                      {order.statut || 'N/A'}
                    </span>
                  </td>
                  <td className="actions-cell">
                    <button 
                      onClick={() => handleCancelOrder(order.id)} 
                      className="cancel-order-btn"
                      disabled={cancellingOrderId === order.id} 
                      title="Annuler la commande"
                    >
                      {cancellingOrderId === order.id ? '...' : '❌'}
                    </button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      )}
    </div>
  );
};

export default PendingOrdersPanel; 